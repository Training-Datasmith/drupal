<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

/**
 * General class for an abstracted INSERT query.
 *
 * @ingroup database
 */
class Insert extends Query implements \Countable
{
    use Insert_Trait;
    /**
     * A SelectQuery object to fetch the rows that should be inserted.
     *
     * @var \Drupal\Core\Database\Query\SelectInterface
     */
    protected $from_query;
    /**
     * Constructs an Insert object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   A Connection object.
     * @param string $table
     *   Name of the table to associate with this query.
     * @param array $options
     *   Array of database options.
     */
    public function __construct(\Drupal\Core\Database\Connection $connection, $table, array $options = [])
    {
        parent::__construct($connection, $options);
        $this->table = $table;
    }
    /**
     * Sets the fromQuery on this InsertQuery object.
     *
     * @param \Drupal\Core\Database\Query\SelectInterface $query
     *   The query to fetch the rows that should be inserted.
     *
     * @return $this
     *   The called object.
     */
    public function from(Select_Interface $query): static
    {
        $this->from_query = $query;
        return $this;
    }
    /**
     * Executes the insert query.
     *
     * @return int|null|string
     *   The last insert ID of the query, if one exists. If the query was given
     *   multiple sets of values to insert, the return value is undefined. If no
     *   fields are specified, this method will do nothing and return NULL. That
     *   That makes it safe to use in multi-insert loops.
     */
    public function execute()
    {
        // If validation fails, simply return NULL. Note that validation routines
        // in preExecute() may throw exceptions instead.
        if (!$this->pre_execute()) {
            return null;
        }
        // If we're selecting from a SelectQuery, finish building the query and
        // pass it back, as any remaining options are irrelevant.
        if (!empty($this->from_query)) {
            $sql = (string) $this;
            // The SelectQuery may contain arguments, load and pass them through.
            return $this->connection->query($sql, $this->from_query->get_arguments(), $this->query_options);
        }
        $last_insert_id = 0;
        $stmt = $this->connection->prepare_statement((string) $this, $this->query_options);
        try {
            // Per https://en.wikipedia.org/wiki/Insert_%28SQL%29#Multirow_inserts,
            // not all databases implement SQL-92's standard syntax for multi-row
            // inserts. Therefore, in the degenerate case, execute a separate query
            // for each row, all within a single transaction for atomicity and
            // performance.
            $transaction = $this->connection->start_transaction();
            foreach ($this->insert_values as $insert_values) {
                $stmt->execute($insert_values, $this->query_options);
                $last_insert_id = $this->connection->last_insert_id();
            }
        } catch (\Exception $e) {
            if (isset($transaction)) {
                // One of the INSERTs failed, rollback the whole batch.
                $transaction->roll_back();
            }
            // Rethrow the exception for the calling code.
            throw $e;
        }
        // Re-initialize the values array so that we can re-use this query.
        $this->insert_values = [];
        // Transaction commits here where $transaction looses scope.
        return $last_insert_id;
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
        // Default fields are always placed first for consistency.
        $insert_fields = array_merge($this->default_fields, $this->insert_fields);
        if (!empty($this->from_query)) {
            return $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') ' . $this->from_query;
        }
        // For simplicity, we will use the $placeholders array to inject
        // default keywords even though they are not, strictly speaking,
        // placeholders for prepared statements.
        $placeholders = [];
        $placeholders = array_pad($placeholders, count($this->default_fields), 'default');
        $placeholders = array_pad($placeholders, count($this->insert_fields), '?');
        return $comments . 'INSERT INTO {' . $this->table . '} (' . implode(', ', $insert_fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    }
    /**
     * Preprocesses and validates the query.
     *
     * @return bool
     *   TRUE if the validation was successful, FALSE if not.
     *
     * @throws \Drupal\Core\Database\Query\FieldsOverlapException
     * @throws \Drupal\Core\Database\Query\NoFieldsException
     */
    protected function pre_execute(): bool
    {
        // Confirm that the user did not try to specify an identical
        // field and default field.
        if (array_intersect($this->insert_fields, $this->default_fields)) {
            throw new Fields_Overlap_Exception('You may not specify the same field to have a value and a schema-default value.');
        }
        if (!empty($this->from_query)) {
            // We have to assume that the used aliases match the insert fields.
            // Regular fields are added to the query before expressions, maintain the
            // same order for the insert fields.
            // This behavior can be overridden by calling fields() manually as only
            // the first call to fields() does have an effect.
            $this->fields(array_merge(array_keys($this->from_query->get_fields()), array_keys($this->from_query->get_expressions())));
        } else if (count($this->insert_fields) + count($this->default_fields) == 0) {
            throw new No_Fields_Exception('There are no fields available to insert with.');
        }
        // If no values have been added, silently ignore this query. This can happen
        // if values are added conditionally, so we don't want to throw an
        // exception.
        if (!isset($this->insert_values[0]) && count($this->insert_fields) > 0 && empty($this->from_query)) {
            return false;
        }
        return true;
    }
}