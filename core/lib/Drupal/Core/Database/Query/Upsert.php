<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * General class for an abstracted "Upsert" (UPDATE or INSERT) query operation.
 *
 * This class can only be used with a table with a single unique index.
 * Often, this will be the primary key. On such a table this class works like
 * Insert except the rows will be set to the desired values even if the key
 * existed before.
 */
abstract class Upsert extends Query implements \Countable
{
    use Insert_Trait;
    /**
     * The unique or primary key of the table.
     *
     * @var string
     */
    protected $key;
    /**
     * Constructs an Upsert object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   A Connection object.
     * @param string $table
     *   Name of the table to associate with this query.
     * @param array $options
     *   (optional) An array of database options.
     */
    public function __construct(Connection $connection, $table, array $options = [])
    {
        parent::__construct($connection, $options);
        $this->table = $table;
    }
    /**
     * Sets the unique / primary key field to be used as condition for this query.
     *
     * @param string $field
     *   The name of the field to set.
     *
     * @return $this
     */
    public function key($field)
    {
        $this->key = $field;
        return $this;
    }
    /**
     * Preprocesses and validates the query.
     *
     * @return bool
     *   TRUE if the validation was successful, FALSE otherwise.
     *
     * @throws \Drupal\Core\Database\Query\NoUniqueFieldException
     * @throws \Drupal\Core\Database\Query\FieldsOverlapException
     * @throws \Drupal\Core\Database\Query\NoFieldsException
     */
    protected function pre_execute()
    {
        // Confirm that the user set the unique/primary key of the table.
        if (!$this->key) {
            throw new No_Unique_Field_Exception('There is no unique field specified.');
        }
        // Confirm that the user did not try to specify an identical
        // field and default field.
        if (array_intersect($this->insert_fields, $this->default_fields)) {
            throw new Fields_Overlap_Exception('You may not specify the same field to have a value and a schema-default value.');
        }
        // Don't execute query without fields.
        if (count($this->insert_fields) + count($this->default_fields) == 0) {
            throw new No_Fields_Exception('There are no fields available to insert with.');
        }
        // If no values have been added, silently ignore this query. This can happen
        // if values are added conditionally, so we don't want to throw an
        // exception.
        return isset($this->insert_values[0]) || $this->insert_fields;
    }
    /**
     * Executes the UPSERT operation.
     *
     * @return int
     *   An integer indicating the number of rows affected by the operation. Do
     *   not rely on this value as a precise indication of the actual rows
     *   affected: different database engines return different values.
     */
    public function execute()
    {
        if (!$this->pre_execute()) {
            return null;
        }
        $max_placeholder = 0;
        $values = [];
        foreach ($this->insert_values as $insert_values) {
            foreach ($insert_values as $value) {
                $values[':db_insert_placeholder_' . $max_placeholder++] = $value;
            }
        }
        $stmt = $this->connection->prepare_statement((string) $this, $this->query_options, true);
        try {
            $stmt->execute($values, $this->query_options);
            $affected_rows = $stmt->row_count();
        } catch (\Exception $e) {
            $this->connection->exception_handler()->handle_execution_exception($e, $stmt, $values, $this->query_options);
        }
        // Re-initialize the values array so that we can re-use this query.
        $this->insert_values = [];
        return $affected_rows;
    }
}