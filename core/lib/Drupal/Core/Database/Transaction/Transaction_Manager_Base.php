<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Transaction;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Database\Transaction_Commit_Failed_Exception;
use Drupal\Core\Database\Transaction_Name_Non_Unique_Exception;
use Drupal\Core\Database\Transaction_Out_Of_Order_Exception;
/**
 * The database transaction manager base class.
 *
 * On many databases transactions cannot nest. Instead, we track nested calls
 * to transactions and collapse them into a single client transaction.
 *
 * Database drivers must implement their own class extending from this, and
 * instantiate it via their Connection::driverTransactionManager() method.
 *
 * @see \Drupal\Core\Database\Connection::driverTransactionManager()
 */
abstract class Transaction_Manager_Base implements Transaction_Manager_Interface
{
    /**
     * The ID of the root Transaction object.
     *
     * The unique identifier of the first 'root' transaction object created, when
     * the stack is empty.
     *
     * Normally, during the transaction stack lifecycle only one 'root'
     * Transaction object is processed. Any post transaction callbacks are only
     * processed during its destruction. However, there are cases when there
     * could be multiple 'root' transaction objects in the stack. For example: a
     * 'root' transaction object is opened, then a DDL statement is executed in a
     * database that does not support transactional DDL, and because of that,
     * another 'root' is opened before the original one is closed.
     *
     * Keeping track of the first 'root' created allows us to process the post
     * transaction callbacks only during its destruction and not during
     * destruction of another one.
     */
    private ?string $root_id = null;
    /**
     * The stack of Drupal transactions currently active.
     *
     * This property is keeping track of the Transaction objects started and
     * ended as a LIFO (Last In, First Out) stack.
     *
     * The database API allows to begin transactions, add an arbitrary number of
     * additional savepoints, and release any savepoint in the sequence. When
     * this happens, the database will implicitly release all the savepoints
     * created after the one released. Given Drupal implementation of the
     * Transaction objects, we cannot force reducing the scope of the
     * corresponding Transaction savepoint objects from the manager, because they
     * live in the scope of the calling code. This stack ensures that when an
     * outlived Transaction object gets out of scope, it will not try to release
     * on the database a savepoint that no longer exists.
     *
     * Differently, rollbacks are strictly being checked for LIFO order: if a
     * rollback is requested against a savepoint that is not the last created,
     * the manager will throw a TransactionOutOfOrderException.
     *
     * The array key is the transaction's unique id, its value a StackItem.
     *
     * @var array<string,StackItem>
     */
    private array $stack = [];
    /**
     * A list of voided stack items.
     *
     * In some cases the active transaction can be automatically committed by the
     * database server (for example, MySql when a DDL statement is executed
     * during a transaction). In such cases we need to void the remaining items
     * on the stack, and we track them here.
     *
     * The array key is the transaction's unique id, its value a StackItem.
     *
     * @var array<string,StackItem>
     */
    private array $voided_items = [];
    /**
     * A list of post-transaction callbacks.
     *
     * @var callable[]
     *
     * @see \Drupal\Core\Database\Transaction\TransactionManagerInterface::addPostTransactionCallback()
     */
    private array $post_transaction_callbacks = [];
    /**
     * The state of the underlying client connection transaction.
     *
     * Note that this is a proxy of the actual state on the database server,
     * best determined through calls to methods in this class. The actual
     * state on the database server could be different.
     */
    private Client_Connection_Transaction_State $connection_transaction_state;
    /**
     * Whether to trigger warnings when unpiling a void transaction.
     *
     * Normally FALSE, is set to TRUE by specific tests checking the internal
     * state of the transaction stack.
     *
     * @internal
     */
    public bool $trigger_warning_when_unpiling_on_void_transaction = false;
    /**
     * Constructor.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     */
    public function __construct(protected readonly Connection $connection)
    {
    }
    /**
     * Destructor.
     *
     * When destructing, $stack must have been already emptied.
     */
    public function __destruct()
    {
        assert($this->stack === [], 'Transaction $stack was not empty. Active stack: ' . $this->dump_stack_items_as_string());
    }
    /**
     * Returns the current depth of the transaction stack.
     *
     * @return int
     *   The current depth of the transaction stack.
     *
     * @todo consider making this function protected.
     *
     * @internal
     */
    public function stack_depth(): int
    {
        return count($this->stack());
    }
    /**
     * Returns the content of the transaction stack.
     *
     * Drivers should not override this method unless they also override the
     * $stack property.
     *
     * @return array<string,StackItem>
     *   The elements of the transaction stack.
     */
    protected function stack(): array
    {
        return $this->stack;
    }
    /**
     * Commits the entire transaction stack.
     *
     * @internal
     *   This method exists only to work around a bug caused by Drupal incorrectly
     *   relying on object destruction order to commit transactions. Xdebug 3.3.0
     *   changes the order of object destruction when the develop mode is enabled.
     */
    public function commit_all(): void
    {
        foreach (array_reverse($this->stack()) as $id => $item) {
            $this->unpile($item->name, $id);
        }
    }
    /**
     * Adds an item to the transaction stack.
     *
     * Drivers should not override this method unless they also override the
     * $stack property.
     *
     * @param string $id
     *   The id of the transaction.
     * @param \Drupal\Core\Database\Transaction\StackItem $item
     *   The stack item.
     */
    protected function add_stack_item(string $id, Stack_Item $item): void
    {
        $this->stack[$id] = $item;
    }
    /**
     * Removes an item from the transaction stack.
     *
     * Drivers should not override this method unless they also override the
     * $stack property.
     *
     * @param string $id
     *   The id of the transaction.
     */
    protected function remove_stack_item(string $id): void
    {
        unset($this->stack[$id]);
    }
    /**
     * Voids an item from the transaction stack.
     *
     * Drivers should not override this method unless they also override the
     * $stack property.
     *
     * @param string $id
     *   The id of the transaction.
     */
    protected function void_stack_item(string $id): void
    {
        // The item should be removed from $stack and added to $voidedItems for
        // later processing.
        if (isset($this->stack[$id])) {
            $this->voided_items[$id] = $this->stack[$id];
        }
        $this->remove_stack_item($id);
    }
    /**
     * Produces a string representation of the stack items.
     *
     * A helper method for exception messages.
     *
     * Drivers should not override this method unless they also override the
     * $stack property.
     *
     * @return string
     *   The string representation of the stack items.
     */
    protected function dump_stack_items_as_string(): string
    {
        if ($this->stack() === []) {
            return '*** empty ***';
        }
        $temp = [];
        foreach ($this->stack() as $id => $item) {
            $temp[] = $id . '\\' . $item->name;
        }
        return implode(' > ', $temp);
    }
    /**
     * {@inheritdoc}
     */
    public function in_transaction(): bool
    {
        return (bool) $this->stack_depth() && $this->get_connection_transaction_state() === Client_Connection_Transaction_State::Active;
    }
    /**
     * {@inheritdoc}
     */
    public function push(string $name = ''): Transaction
    {
        if (!$this->in_transaction()) {
            // If there is no transaction active, name the transaction
            // 'drupal_transaction'.
            $name = 'drupal_transaction';
        } elseif (!$name) {
            // Within transactions, savepoints are used. Each savepoint requires a
            // name. So if no name is present we need to create one.
            $name = 'savepoint_' . $this->stack_depth();
        }
        if ($this->has($name)) {
            throw new Transaction_Name_Non_Unique_Exception("A transaction named {$name} is already in use. Active stack: " . $this->dump_stack_items_as_string());
        }
        // Define a unique ID for the transaction.
        $id = uniqid('', true);
        // Do the client-level processing.
        if ($this->stack_depth() === 0) {
            $this->begin_client_transaction();
            $type = Stack_Item_Type::Root;
            $this->set_connection_transaction_state(Client_Connection_Transaction_State::Active);
            // Only set ::rootId if there's not one set already, which may happen in
            // case of broken transactions.
            if ($this->root_id === null) {
                $this->root_id = $id;
            }
        } else {
            // If we're already in a Drupal transaction then we want to create a
            // database savepoint, rather than try to begin another database
            // transaction.
            $this->add_client_savepoint($name);
            $type = Stack_Item_Type::Savepoint;
        }
        // Add an item on the stack, increasing its depth.
        $this->add_stack_item($id, new Stack_Item($name, $type));
        // Actually return a new Transaction object.
        return new Transaction($this->connection, $name, $id);
    }
    /**
     * {@inheritdoc}
     */
    public function purge(string $name, string $id): void
    {
        // If this is a 'root' transaction, and it is voided (that is, no longer in
        // the stack), then the transaction on the database is no longer active. An
        // action such as a commit, a release savepoint, a rollback, or a DDL
        // statement, was executed that terminated the database transaction. So, we
        // can process the post transaction callbacks.
        if (!isset($this->stack()[$id]) && isset($this->voided_items[$id]) && $this->root_id === $id) {
            $this->process_post_transaction_callbacks();
            $this->root_id = null;
            unset($this->voided_items[$id]);
            return;
        }
        // If the $id does not correspond to the one in the stack for that $name,
        // we are facing an orphaned Transaction object (for example in case of a
        // DDL statement breaking an active transaction). That should be listed in
        // $voidedItems, so we can remove it from there.
        if (!isset($this->stack()[$id]) || $this->stack()[$id]->name !== $name) {
            unset($this->voided_items[$id]);
            return;
        }
        // When we get here, the transaction (or savepoint) is still active on the
        // database. We can unpile it, and if we are left with no more items in the
        // stack, we can also process the post transaction callbacks.
        $this->commit($name, $id);
        $this->remove_stack_item($id);
        if ($this->root_id === $id) {
            $this->process_post_transaction_callbacks();
            $this->root_id = null;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function unpile(string $name, string $id): void
    {
        // If the transaction was voided, we cannot unpile. Skip but trigger a user
        // warning if requested.
        if ($this->get_connection_transaction_state() === Client_Connection_Transaction_State::Voided) {
            if ($this->trigger_warning_when_unpiling_on_void_transaction) {
                trigger_error('Transaction::commitOrRelease() was not processed because a prior execution of a DDL statement already committed the transaction.', E_USER_WARNING);
            }
            return;
        }
        // If there is no $id to commit, or if $id does not correspond to the one
        // in the stack for that $name, the commit is out of order.
        if (!isset($this->stack()[$id]) || $this->stack()[$id]->name !== $name) {
            throw new Transaction_Out_Of_Order_Exception("Error attempting commit of {$id}\\{$name}. Active stack: " . $this->dump_stack_items_as_string());
        }
        // Commit the transaction.
        $this->commit($name, $id);
        // Void the transaction stack item.
        $this->void_stack_item($id);
    }
    /**
     * Commits a Drupal transaction.
     *
     * @param string $name
     *   The name of the transaction.
     * @param string $id
     *   The id of the transaction.
     *
     * @throws \Drupal\Core\Database\TransactionOutOfOrderException
     *   If a Drupal Transaction with the specified name does not exist.
     * @throws \Drupal\Core\Database\TransactionCommitFailedException
     *   If the commit of the root transaction failed.
     */
    protected function commit(string $name, string $id): void
    {
        if ($this->get_connection_transaction_state() !== Client_Connection_Transaction_State::Active) {
            // The stack got corrupted.
            throw new Transaction_Out_Of_Order_Exception("Transaction {$id}\\{$name} is out of order. Active stack: " . $this->dump_stack_items_as_string());
        }
        // If we are not releasing the last savepoint but an earlier one, or
        // committing a root transaction while savepoints are active, all
        // subsequent savepoints will be released as well. The stack must be
        // diminished accordingly.
        while (($i = array_key_last($this->stack())) != $id) {
            $this->void_stack_item((string) $i);
        }
        if ($this->stack_depth() > 1 && $this->stack()[$id]->type === Stack_Item_Type::Savepoint) {
            // Release the client transaction savepoint in case the Drupal
            // transaction is not a root one.
            $this->release_client_savepoint($name);
        } elseif ($this->stack_depth() === 1 && $this->stack()[$id]->type === Stack_Item_Type::Root) {
            // If this was the root Drupal transaction, we can commit the client
            // transaction.
            $this->process_root_commit();
        } else {
            // The stack got corrupted.
            throw new Transaction_Out_Of_Order_Exception("Transaction {$id}/{$name} is out of order. Active stack: " . $this->dump_stack_items_as_string());
        }
    }
    /**
     * {@inheritdoc}
     */
    public function rollback(string $name, string $id): void
    {
        // If the transaction was voided, we cannot rollback. Fail silently but
        // trigger a user warning.
        if ($this->get_connection_transaction_state() === Client_Connection_Transaction_State::Voided) {
            $this->connection_transaction_state = Client_Connection_Transaction_State::RollbackFailed;
            trigger_error('Transaction::rollBack() failed because of a prior execution of a DDL statement.', E_USER_WARNING);
            return;
        }
        // Rolled back item should match the last one in stack.
        if ($id != array_key_last($this->stack()) || $name !== $this->stack()[$id]->name) {
            throw new Transaction_Out_Of_Order_Exception("Error attempting rollback of {$id}\\{$name}. Active stack: " . $this->dump_stack_items_as_string());
        }
        if ($this->get_connection_transaction_state() === Client_Connection_Transaction_State::Active) {
            if ($this->stack_depth() > 1 && $this->stack()[$id]->type === Stack_Item_Type::Savepoint) {
                // Rollback the client transaction to the savepoint when the Drupal
                // transaction is not a root one. Then, release the savepoint too. The
                // client connection remains active.
                $this->rollback_client_savepoint($name);
                $this->release_client_savepoint($name);
                // The Transaction object remains open, and when it will get destructed
                // no commit should happen. Void the stack item.
                $this->void_stack_item($id);
            } elseif ($this->stack_depth() === 1 && $this->stack()[$id]->type === Stack_Item_Type::Root) {
                // If this was the root Drupal transaction, we can rollback the client
                // transaction. The transaction is closed.
                $this->process_root_rollback();
                if ($this->get_connection_transaction_state() === Client_Connection_Transaction_State::RolledBack) {
                    // The Transaction object remains open, and when it will get
                    // destructed no commit should happen. Void the stack item.
                    $this->void_stack_item($id);
                }
            } else {
                // The stack got corrupted.
                throw new Transaction_Out_Of_Order_Exception("Error attempting rollback of {$id}\\{$name}. Active stack: " . $this->dump_stack_items_as_string());
            }
            return;
        }
        // The stack got corrupted.
        throw new Transaction_Out_Of_Order_Exception("Error attempting rollback of {$id}\\{$name}. Active stack: " . $this->dump_stack_items_as_string());
    }
    /**
     * {@inheritdoc}
     */
    public function add_post_transaction_callback(callable $callback): void
    {
        if (!$this->in_transaction()) {
            throw new \LogicException('Root transaction end callbacks can only be added when there is an active transaction.');
        }
        $this->post_transaction_callbacks[] = $callback;
    }
    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        foreach ($this->stack() as $item) {
            if ($item->name === $name) {
                return true;
            }
        }
        return false;
    }
    /**
     * Sets the state of the client connection transaction.
     *
     * Note that this is a proxy of the actual state on the database server,
     * best determined through calls to methods in this class. The actual
     * state on the database server could be different.
     *
     * Drivers should not override this method unless they also override the
     * $connectionTransactionState property.
     *
     * @param \Drupal\Core\Database\Transaction\ClientConnectionTransactionState $state
     *   The state of the client connection.
     */
    protected function set_connection_transaction_state(Client_Connection_Transaction_State $state): void
    {
        $this->connection_transaction_state = $state;
    }
    /**
     * Gets the state of the client connection transaction.
     *
     * Note that this is a proxy of the actual state on the database server,
     * best determined through calls to methods in this class. The actual
     * state on the database server could be different.
     *
     * Drivers should not override this method unless they also override the
     * $connectionTransactionState property.
     *
     * @return \Drupal\Core\Database\Transaction\ClientConnectionTransactionState
     *   The state of the client connection.
     */
    protected function get_connection_transaction_state(): Client_Connection_Transaction_State
    {
        return $this->connection_transaction_state;
    }
    /**
     * Processes the root transaction rollback.
     */
    protected function process_root_rollback(): void
    {
        $this->rollback_client_transaction();
    }
    /**
     * Processes the root transaction commit.
     *
     * @throws \Drupal\Core\Database\TransactionCommitFailedException
     *   If the commit of the root transaction failed.
     */
    protected function process_root_commit(): void
    {
        $client_commit = $this->commit_client_transaction();
        if (!$client_commit) {
            throw new Transaction_Commit_Failed_Exception();
        }
    }
    /**
     * Processes the post-transaction callbacks.
     */
    protected function process_post_transaction_callbacks(): void
    {
        if (!empty($this->post_transaction_callbacks)) {
            $callbacks = $this->post_transaction_callbacks;
            $this->post_transaction_callbacks = [];
            foreach ($callbacks as $callback) {
                call_user_func($callback, $this->get_connection_transaction_state() === Client_Connection_Transaction_State::Committed || $this->get_connection_transaction_state() === Client_Connection_Transaction_State::Voided);
            }
        }
    }
    /**
     * Begins a transaction on the client connection.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     */
    abstract protected function begin_client_transaction(): bool;
    /**
     * Adds a savepoint on the client transaction.
     *
     * This is a generic implementation. Drivers should override this method
     * to use a method specific for their client connection.
     *
     * @param string $name
     *   The name of the savepoint.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     */
    protected function add_client_savepoint(string $name): bool
    {
        $this->connection->query('SAVEPOINT ' . $name);
        return true;
    }
    /**
     * Rolls back to a savepoint on the client transaction.
     *
     * This is a generic implementation. Drivers should override this method
     * to use a method specific for their client connection.
     *
     * @param string $name
     *   The name of the savepoint.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     */
    protected function rollback_client_savepoint(string $name): bool
    {
        $this->connection->query('ROLLBACK TO SAVEPOINT ' . $name);
        return true;
    }
    /**
     * Releases a savepoint on the client transaction.
     *
     * This is a generic implementation. Drivers should override this method
     * to use a method specific for their client connection.
     *
     * @param string $name
     *   The name of the savepoint.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     */
    protected function release_client_savepoint(string $name): bool
    {
        $this->connection->query('RELEASE SAVEPOINT ' . $name);
        return true;
    }
    /**
     * Rolls back a client transaction.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     */
    abstract protected function rollback_client_transaction(): bool;
    /**
     * Commits a client transaction.
     *
     * @return bool
     *   Returns TRUE on success or FALSE on failure.
     */
    abstract protected function commit_client_transaction(): bool;
    /**
     * {@inheritdoc}
     */
    public function void_client_transaction(): void
    {
        while ($i = array_key_last($this->stack())) {
            $this->void_stack_item((string) $i);
        }
        $this->set_connection_transaction_state(Client_Connection_Transaction_State::Voided);
    }
}