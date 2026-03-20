<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
/**
 * Base class for query builders.
 *
 * Note that query builders use PHP's magic __toString() method to compile the
 * query object into a prepared statement.
 */
abstract class Query implements Placeholder_Interface, \Stringable
{
    /**
     * The target of the connection object.
     *
     * @var string
     */
    protected $connection_target;
    /**
     * The key of the connection object.
     *
     * @var string
     */
    protected $connection_key;
    /**
     * A unique identifier for this query object.
     */
    protected string $unique_identifier;
    /**
     * The placeholder counter.
     *
     * @var int
     */
    protected $next_placeholder = 0;
    /**
     * An array of comments that can be prepended to a query.
     *
     * @var array
     */
    protected $comments = [];
    /**
     * Constructs a Query object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   Database connection object.
     * @param array $queryOptions
     *   Array of query options.
     */
    public function __construct(
        protected \Drupal\Core\Database\Connection $connection,
        /**
         * The query options to pass on to the connection object.
         */
        protected $query_options
    )
    {
        $this->unique_identifier = uniqid('', true);
        $this->connection_key = $this->connection->get_key();
        $this->connection_target = $this->connection->get_target();
    }
    /**
     * Implements the magic __sleep function to disconnect from the database.
     */
    public function __sleep(): array
    {
        $keys = get_object_vars($this);
        unset($keys['connection']);
        return array_keys($keys);
    }
    /**
     * Implements the magic __wakeup function to reconnect to the database.
     */
    public function __wakeup(): void
    {
        $this->connection = Database::get_connection($this->connection_target, $this->connection_key);
    }
    /**
     * Implements the magic __clone function.
     */
    public function __clone()
    {
        $this->unique_identifier = uniqid('', true);
    }
    /**
     * Runs the query against the database.
     *
     * @return \Drupal\Core\Database\StatementInterface|null
     *   A prepared statement, or NULL if the query is not valid.
     */
    abstract protected function execute();
    /**
     * Implements PHP magic __toString method to convert the query to a string.
     *
     * The toString operation is how we compile a query object to a prepared
     * statement.
     *
     * @return string
     *   A prepared statement query string for this object.
     *
     * @throws \BadMethodCallException
     *   Thrown when the operation is a Merge or the operation is not implemented,
     *   as in test.
     */
    abstract public function __toString(): string;
    /**
     * Returns a unique identifier for this object.
     */
    public function unique_identifier()
    {
        return $this->unique_identifier;
    }
    /**
     * Gets the next placeholder value for this query object.
     *
     * @return int
     *   The next placeholder value.
     */
    public function next_placeholder()
    {
        return $this->next_placeholder++;
    }
    /**
     * Adds a comment to the query.
     *
     * By adding a comment to a query, you can more easily find it in your
     * query log or the list of active queries on an SQL server. This allows
     * for easier debugging and allows you to more easily find where a query
     * with a performance problem is being generated.
     *
     * The comment string will be sanitized to remove * / and other characters
     * that may terminate the string early so as to avoid SQL injection attacks.
     *
     * @param string $comment
     *   The comment string to be inserted into the query.
     *
     * @return $this
     */
    public function comment($comment)
    {
        $this->comments[] = $comment;
        return $this;
    }
    /**
     * Returns a reference to the comments array for the query.
     *
     * Because this method returns by reference, alter hooks may edit the comments
     * array directly to make their changes. If just adding comments, however, the
     * use of comment() is preferred.
     *
     * Note that this method must be called by reference as well:
     * @code
     * $comments =& $query->getComments();
     * @endcode
     *
     * @return array
     *   A reference to the comments array structure.
     */
    public function &get_comments()
    {
        return $this->comments;
    }
    /**
     * Gets the database connection to be used for the query.
     *
     * @return \Drupal\Core\Database\Connection
     *   The database connection to be used for the query.
     */
    public function get_connection()
    {
        return $this->connection;
    }
}