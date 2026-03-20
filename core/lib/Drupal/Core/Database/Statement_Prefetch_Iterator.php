<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\Fetch_As;
use Drupal\Core\Database\Statement\Pdo_Trait;
use Drupal\Core\Database\Statement\Prefetched_Result;
use Drupal\Core\Database\Statement\Statement_Base;
/**
 * An implementation of StatementInterface that prefetches all data.
 *
 * This class behaves very similar to a StatementWrapperIterator of a
 * \PDOStatement but as it always fetches every row it is possible to
 * manipulate those results.
 */
class Statement_Prefetch_Iterator extends Statement_Base
{
    use Pdo_Trait;
    /**
     * Constructs a StatementPrefetchIterator object.
     *
     * @param object $clientConnection
     *   Client database connection object, for example \PDO.
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     * @param string $queryString
     *   The query string.
     * @param array $driverOptions
     *   Driver-specific options.
     * @param bool $rowCountEnabled
     *   (optional) Enables counting the rows matched. Defaults to FALSE.
     */
    public function __construct(object $client_connection, Connection $connection, string $query_string, protected array $driver_options = [], bool $row_count_enabled = false)
    {
        parent::__construct($connection, $client_connection, $query_string, $row_count_enabled);
    }
    /**
     * Returns the client-level database PDO statement object.
     *
     * This method should normally be used only within database driver code.
     *
     * @return \PDOStatement
     *   The client-level database PDO statement.
     *
     * @throws \RuntimeException
     *   If the client-level statement is not set.
     */
    public function get_client_statement(): \PDOStatement
    {
        if (isset($this->client_statement)) {
            assert($this->client_statement instanceof \PDOStatement);
            return $this->client_statement;
        }
        throw new \LogicException('\PDOStatement not initialized');
    }
    /**
     * {@inheritdoc}
     */
    public function execute($args = [], $options = [])
    {
        assert(!isset($options['fetch']) || $options['fetch'] instanceof Fetch_As || is_string($options['fetch']), 'The "fetch" option passed to execute() must contain a FetchAs enum case or a string. See https://www.drupal.org/node/3488338');
        $start_event = $this->dispatch_statement_execution_start_event($args ?? []);
        // Prepare and execute the statement.
        try {
            $this->client_statement = $this->get_statement($this->query_string, $args);
            $return = $this->client_execute($args, $options);
        } catch (\Exception $e) {
            $this->dispatch_statement_execution_failure_event($start_event, $e);
            // @phpstan-ignore unset.possiblyHookedProperty
            unset($this->client_statement);
            throw $e;
        }
        // Fetch all the data from the reply, in order to release any lock as soon
        // as possible. Then, destroy the client statement. See the documentation
        // of \Drupal\sqlite\Driver\Database\sqlite\Statement for an explanation.
        $this->result = new Prefetched_Result($this->fetch_mode, $this->fetch_options, $this->client_fetch_all(Fetch_As::Associative), $this->row_count_enabled ? $this->client_row_count() : null);
        // @phpstan-ignore unset.possiblyHookedProperty
        unset($this->client_statement);
        $this->mark_resultset_iterable($return);
        if (isset($options['fetch'])) {
            if (is_string($options['fetch'])) {
                // Default to an object. Note: db fields will be added to the object
                // before the constructor is run. If you need to assign fields after
                // the constructor is run. See https://www.drupal.org/node/315092.
                $this->set_fetch_mode(Fetch_As::ClassObject, $options['fetch']);
            } else {
                $this->set_fetch_mode($options['fetch']);
            }
        }
        $this->dispatch_statement_execution_end_event($start_event);
        return $return;
    }
    /**
     * Grab a PDOStatement object from a given query and its arguments.
     *
     * Some drivers (including SQLite) will need to perform some preparation
     * themselves to get the statement right.
     *
     * @param string $query
     *   The query.
     * @param array|null $args
     *   An array of arguments. This can be NULL.
     *
     * @return object
     *   A PDOStatement object.
     */
    protected function get_statement(string $query, ?array &$args = []): object
    {
        return $this->connection->prepare($query, $this->driver_options);
    }
}