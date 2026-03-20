<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * General class for an abstracted TRUNCATE operation.
 */
class Truncate extends Query
{
    /**
     * Constructs a Truncate query object.
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
         * The table to truncate.
         */
        protected $table,
        array $options = []
    )
    {
        parent::__construct($connection, $options);
    }
    /**
     * Executes the TRUNCATE query.
     *
     * In most cases, TRUNCATE is not a transaction safe statement as it is a DDL
     * statement which results in an implicit COMMIT. When we are in a
     * transaction, fallback to the slower, but transactional, DELETE.
     * PostgreSQL also locks the entire table for a TRUNCATE strongly reducing
     * the concurrency with other transactions.
     *
     * @return int|null
     *   Return value is dependent on whether the executed SQL statement is a
     *   TRUNCATE or a DELETE. TRUNCATE is DDL and no information on affected
     *   rows is available. DELETE is DML and will return the number of affected
     *   rows. In general, do not rely on the value returned by this method in
     *   calling code.
     *
     * @see https://learnsql.com/blog/difference-between-truncate-delete-and-drop-table-in-sql
     */
    public function execute()
    {
        $stmt = $this->connection->prepare_statement((string) $this, $this->query_options, true);
        try {
            $stmt->execute([], $this->query_options);
            return $stmt->row_count();
        } catch (\Exception $e) {
            $this->connection->exception_handler()->handle_execution_exception($e, $stmt, [], $this->query_options);
        }
        return null;
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
        // The statement actually built depends on whether a transaction is active.
        // @see ::execute()
        if ($this->connection->in_transaction()) {
            return $comments . 'DELETE FROM {' . $this->connection->escape_table($this->table) . '}';
        }
        return $comments . 'TRUNCATE {' . $this->connection->escape_table($this->table) . '} ';
    }
}