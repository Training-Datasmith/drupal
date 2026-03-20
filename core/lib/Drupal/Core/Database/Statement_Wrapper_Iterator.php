<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

use Drupal\Core\Database\Statement\Fetch_As;
use Drupal\Core\Database\Statement\Pdo_Result;
use Drupal\Core\Database\Statement\Pdo_Trait;
use Drupal\Core\Database\Statement\Statement_Base;
/**
 * StatementInterface iterator implementation.
 */
class Statement_Wrapper_Iterator extends Statement_Base
{
    use Pdo_Trait;
    /**
     * Constructs a StatementWrapperIterator object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   Drupal database connection object.
     * @param object $clientConnection
     *   Client database connection object, for example \PDO.
     * @param string $query
     *   The SQL query string.
     * @param array $options
     *   Array of query options.
     * @param bool $rowCountEnabled
     *   (optional) Enables counting the rows matched. Defaults to FALSE.
     */
    public function __construct(Connection $connection, object $client_connection, string $query, array $options, bool $row_count_enabled = false)
    {
        parent::__construct($connection, $client_connection, $query, $row_count_enabled);
        $this->client_statement = $this->client_connection->prepare($query, $options);
        $this->set_fetch_mode(Fetch_As::Object);
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
        if (isset($options['fetch'])) {
            if (is_string($options['fetch'])) {
                $this->set_fetch_mode(Fetch_As::ClassObject, $options['fetch']);
            } else {
                $this->set_fetch_mode($options['fetch']);
            }
        }
        $start_event = $this->dispatch_statement_execution_start_event($args ?? []);
        try {
            $return = $this->client_execute($args, $options);
            $this->result = new Pdo_Result($this->fetch_mode, $this->fetch_options, $this->get_client_statement());
            $this->mark_resultset_iterable($return);
        } catch (\Exception $e) {
            $this->dispatch_statement_execution_failure_event($start_event, $e);
            throw $e;
        }
        $this->dispatch_statement_execution_end_event($start_event);
        return $return;
    }
}