<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * General class for an abstracted DELETE operation.
 *
 * @ingroup database
 */
class Delete extends Query implements Condition_Interface
{
    use Query_Condition_Trait;
    /**
     * Constructs a Delete object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   A Connection object.
     * @param string $table
     *   Name of the table to associate with this query.
     * @param array $options
     *   Array of database options.
     */
    public function __construct(
        Connection $connection,
        /**
         * The table from which to delete.
         */
        protected $table,
        array $options = []
    )
    {
        parent::__construct($connection, $options);
        $this->condition = $this->connection->condition('AND');
    }
    /**
     * Executes the DELETE query.
     *
     * @return int
     *   The number of rows affected by the delete query.
     */
    public function execute()
    {
        $values = [];
        if (count($this->condition)) {
            $this->condition->compile($this->connection, $this);
            $values = $this->condition->arguments();
        }
        $stmt = $this->connection->prepare_statement((string) $this, $this->query_options, true);
        try {
            $stmt->execute($values, $this->query_options);
            return $stmt->row_count();
        } catch (\Exception $e) {
            $this->connection->exception_handler()->handle_execution_exception($e, $stmt, $values, $this->query_options);
        }
    }
    /**
     * Implements PHP magic __toString method to convert the query to a string.
     *
     * @return string
     *   The prepared statement.
     */
    public function __toString(): string
    {
        // Create a sanitized comment string to prepend to the query.
        $comments = $this->connection->make_comment($this->comments);
        $query = $comments . 'DELETE FROM {' . $this->connection->escape_table($this->table) . '} ';
        if (count($this->condition)) {
            $this->condition->compile($this->connection, $this);
            $query .= "\nWHERE " . $this->condition;
        }
        return $query;
    }
}