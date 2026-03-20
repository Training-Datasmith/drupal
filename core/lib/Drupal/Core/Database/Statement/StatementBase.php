<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Statement;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\Statement_Execution_End_Event;
use Drupal\Core\Database\Event\Statement_Execution_Failure_Event;
use Drupal\Core\Database\Event\Statement_Execution_Start_Event;
use Drupal\Core\Database\Fetch_Mode_Trait;
use Drupal\Core\Database\Row_Count_Exception;
use Drupal\Core\Database\Statement_Interface;
use Drupal\Core\Database\Statement_Iterator_Trait;
/**
 * StatementInterface base implementation.
 *
 * This class is meant to be generic enough for any type of database client,
 * even if all Drupal core database drivers currently use PDO clients. We
 * implement \Iterator instead of \IteratorAggregate to allow iteration to be
 * kept in sync with the underlying database resultset cursor. PDO is not able
 * to execute a database operation while a cursor is open on the result of an
 * earlier select query, so Drupal by default uses buffered queries setting
 * \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY to TRUE on the connection. This forces
 * the query to return all the results in a buffer local to the client library,
 * potentially leading to memory issues in case of large datasets being
 * returned by a query. Other database clients, however, could allow
 * multithread queries, or developers could disable buffered queries in PDO:
 * in that case, this class prevents the resultset to be entirely fetched in
 * PHP memory (that an \IteratorAggregate implementation would force) and
 * therefore optimize memory usage while iterating the resultset.
 */
abstract class Statement_Base implements \Iterator, Statement_Interface
{
    use Fetch_Mode_Trait;
    use Statement_Iterator_Trait;
    /**
     * The client database Statement object.
     *
     * For a \PDO client connection, this will be a \PDOStatement object.
     */
    protected ?object $client_statement = null;
    /**
     * The results of a data query language (DQL) statement.
     */
    protected ?Result_Base $result = null;
    /**
     * Holds the default fetch mode.
     */
    protected Fetch_As $fetch_mode = Fetch_As::Object;
    /**
     * Holds fetch options.
     *
     * @var array{'class': class-string, 'constructor_args': list<mixed>, 'column': int}
     */
    protected array $fetch_options = ['class' => 'stdClass', 'constructor_args' => [], 'column' => 0];
    /**
     * Constructor.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   Drupal database connection object.
     * @param object $clientConnection
     *   Client database connection object, for example \PDO.
     * @param string $queryString
     *   The query string.
     * @param bool $rowCountEnabled
     *   (optional) Enables counting the rows matched. Defaults to FALSE.
     */
    public function __construct(protected readonly Connection $connection, protected readonly object $client_connection, protected readonly string $query_string, protected readonly bool $row_count_enabled = false)
    {
    }
    /**
     * Determines if the client-level database statement object exists.
     *
     * This method should normally be used only within database driver code.
     *
     * @return bool
     *   TRUE if the client statement exists, FALSE otherwise.
     */
    public function has_client_statement(): bool
    {
        return isset($this->client_statement);
    }
    /**
     * Returns the client-level database statement object.
     *
     * This method should normally be used only within database driver code.
     *
     * @return object
     *   The client-level database statement.
     *
     * @throws \RuntimeException
     *   If the client-level statement is not set.
     */
    public function get_client_statement(): object
    {
        if ($this->has_client_statement()) {
            return $this->client_statement;
        }
        throw new \LogicException('Client statement not initialized');
    }
    /**
     * {@inheritdoc}
     */
    public function get_connection_target(): string
    {
        return $this->connection->get_target();
    }
    /**
     * {@inheritdoc}
     */
    abstract public function execute($args = [], $options = []);
    /**
     * Dispatches an event informing that the statement execution begins.
     *
     * @param array $args
     *   An array of values with as many elements as there are bound parameters in
     *   the SQL statement being executed. This can be empty.
     *
     * @return \Drupal\Core\Database\Event\StatementExecutionStartEvent|null
     *   The dispatched event or NULL if event dispatching is not enabled.
     */
    protected function dispatch_statement_execution_start_event(array $args): ?Statement_Execution_Start_Event
    {
        if ($this->connection->is_event_enabled(Statement_Execution_Start_Event::class)) {
            $start_event = new Statement_Execution_Start_Event(spl_object_id($this), $this->connection->get_key(), $this->connection->get_target(), $this->get_query_string(), $args, $this->connection->find_caller_from_debug_backtrace());
            $this->connection->dispatch_event($start_event);
            return $start_event;
        }
        return null;
    }
    /**
     * Dispatches an event informing that the statement execution succeeded.
     *
     * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent|null $startEvent
     *   The start event or NULL if event dispatching is not enabled.
     */
    protected function dispatch_statement_execution_end_event(?Statement_Execution_Start_Event $start_event): void
    {
        if (isset($start_event) && $this->connection->is_event_enabled(Statement_Execution_End_Event::class)) {
            $this->connection->dispatch_event(new Statement_Execution_End_Event($start_event->statement_object_id, $start_event->key, $start_event->target, $start_event->query_string, $start_event->args, $start_event->caller, $start_event->time));
        }
    }
    /**
     * Dispatches an event informing of the statement execution failure.
     *
     * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent|null $startEvent
     *   The start event or NULL if event dispatching is not enabled.
     * @param \Exception $e
     *   The statement exception thrown.
     */
    protected function dispatch_statement_execution_failure_event(?Statement_Execution_Start_Event $start_event, \Exception $e): void
    {
        if (isset($start_event) && $this->connection->is_event_enabled(Statement_Execution_Failure_Event::class)) {
            $this->connection->dispatch_event(new Statement_Execution_Failure_Event($start_event->statement_object_id, $start_event->key, $start_event->target, $start_event->query_string, $start_event->args, $start_event->caller, $start_event->time, $e::class, $e->get_code(), $e->get_message()));
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_query_string()
    {
        return $this->query_string;
    }
    /**
     * {@inheritdoc}
     */
    public function set_fetch_mode($mode, $a1 = null, $a2 = [])
    {
        assert($mode instanceof Fetch_As);
        $this->fetch_mode = $mode;
        switch ($mode) {
            case Fetch_As::ClassObject:
                $this->fetch_options['class'] = $a1;
                if ($a2) {
                    $this->fetch_options['constructor_args'] = $a2;
                }
                break;
            case Fetch_As::Column:
                $this->fetch_options['column'] = $a1;
                break;
        }
        // If the result object is missing, just do with the properties setting.
        try {
            if ($this->result) {
                return $this->result->set_fetch_mode($mode, $this->fetch_options);
            }
            return true;
        } catch (\LogicException) {
            return true;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function fetch($mode = null, $cursor_orientation = null, $cursor_offset = null)
    {
        assert($mode === null || $mode instanceof Fetch_As);
        $fetch_options = match (func_num_args()) {
            0 => $this->fetch_options,
            1 => $this->fetch_options,
            2 => $this->fetch_options + ['cursor_orientation' => $cursor_orientation],
            default => $this->fetch_options + ['cursor_orientation' => $cursor_orientation, 'cursor_offset' => $cursor_offset],
        };
        $row = $this->result->fetch($mode ?? $this->fetch_mode, $fetch_options);
        if ($row === false) {
            $this->mark_resultset_fetching_complete();
            return false;
        }
        $this->set_resultset_current_row($row);
        return $row;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_object(?string $class_name = null, array $constructor_arguments = [])
    {
        $row = $class_name === null ? $this->result->fetch(Fetch_As::Object, []) : $this->result->fetch(Fetch_As::ClassObject, ['class' => $class_name, 'constructor_args' => $constructor_arguments]);
        if ($row === false) {
            $this->mark_resultset_fetching_complete();
            return false;
        }
        $this->set_resultset_current_row($row);
        return $row;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_assoc()
    {
        return $this->fetch(Fetch_As::Associative);
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_field($index = 0)
    {
        $column = $this->result->fetch(Fetch_As::Column, ['column' => $index]);
        if ($column === false) {
            $this->mark_resultset_fetching_complete();
            return false;
        }
        $this->set_resultset_current_row($column);
        return $column;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_all($mode = null, $column_index = null, $constructor_arguments = null)
    {
        assert($mode === null || $mode instanceof Fetch_As);
        $fetch_mode = $mode ?? $this->fetch_mode;
        if (isset($column_index)) {
            $this->fetch_options['column'] = $column_index;
        }
        if (isset($constructor_arguments)) {
            $this->fetch_options['constructor_args'] = $constructor_arguments;
        }
        $return = $this->result->fetch_all($fetch_mode, $this->fetch_options);
        $this->mark_resultset_fetching_complete();
        return $return;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_col($index = 0)
    {
        return $this->fetch_all(Fetch_As::Column, $index);
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_all_assoc($key, $fetch = null)
    {
        assert($fetch === null || $fetch instanceof Fetch_As);
        $result = $this->result->fetch_all_assoc($key, $fetch ?? $this->fetch_mode, $this->fetch_options);
        $this->mark_resultset_fetching_complete();
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function fetch_all_keyed($key_index = 0, $value_index = 1)
    {
        $result = $this->result->fetch_all_keyed($key_index, $value_index);
        $this->mark_resultset_fetching_complete();
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function row_count()
    {
        // SELECT query should not use the method.
        if ($this->row_count_enabled) {
            return $this->result->row_count();
        }
        throw new Row_Count_Exception();
    }
}