<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * General class for an abstracted UPDATE operation.
 *
 * @ingroup database
 */
class Update extends Query implements Condition_Interface
{
    use Query_Condition_Trait;
    /**
     * An array of fields that will be updated.
     *
     * @var array
     */
    protected $fields = [];
    /**
     * An array of values to update to.
     *
     * @var array
     */
    protected $arguments = [];
    /**
     * Array of fields to update to an expression in case of a duplicate record.
     *
     * @var array
     *
     * This variable is a nested array in the following format:
     * @code
     * <some field> => [
     *  'condition' => <condition to execute, as a string>,
     *  'arguments' => <array of arguments for condition, or NULL for none>,
     * ];
     * @endcode
     */
    protected $expression_fields = [];
    /**
     * Constructs an Update query object.
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
         * The table to update.
         */
        protected $table,
        array $options = []
    )
    {
        parent::__construct($connection, $options);
        $this->condition = $this->connection->condition('AND');
    }
    /**
     * Adds a set of field->value pairs to be updated.
     *
     * @param array $fields
     *   An associative array of fields to write into the database. The array keys
     *   are the field names and the values are the values to which to set them.
     *
     * @return $this
     *   The called object.
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;
        return $this;
    }
    /**
     * Specifies fields to be updated as an expression.
     *
     * Expression fields are cases such as counter=counter+1. This method takes
     * precedence over fields().
     *
     * @param string $field
     *   The field to set.
     * @param string $expression
     *   The field will be set to the value of this expression. This parameter
     *   may include named placeholders.
     * @param array|null $arguments
     *   If specified, this is an array of key/value pairs for named placeholders
     *   corresponding to the expression.
     *
     * @return $this
     *   The called object.
     */
    public function expression($field, $expression, ?array $arguments = null): static
    {
        $this->expression_fields[$field] = ['expression' => $expression, 'arguments' => $arguments];
        return $this;
    }
    /**
     * Executes the UPDATE query.
     *
     * @return int|null
     *   The number of rows matched by the update query. This includes rows that
     *   actually didn't have to be updated because the values didn't change.
     */
    public function execute()
    {
        [$args, $update_values] = $this->get_query_arguments();
        $update_values += $args;
        if (count($this->condition)) {
            $this->condition->compile($this->connection, $this);
            $update_values = array_merge($update_values, $this->condition->arguments());
        }
        $stmt = $this->connection->prepare_statement((string) $this, $this->query_options, true);
        try {
            $stmt->execute($update_values, $this->query_options);
            return $stmt->row_count();
        } catch (\Exception $e) {
            $this->connection->exception_handler()->handle_execution_exception($e, $stmt, $update_values, $this->query_options);
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
        // Expressions take priority over literal fields, so we process those first
        // and remove any literal fields that conflict.
        $fields = $this->fields;
        $update_fields = [];
        foreach ($this->expression_fields as $field => $data) {
            if ($data['expression'] instanceof Select_Interface) {
                // Compile and cast expression subquery to a string.
                $data['expression']->compile($this->connection, $this);
                $data['expression'] = ' (' . $data['expression'] . ')';
            }
            $update_fields[] = $this->connection->escape_field($field) . '=' . $data['expression'];
            unset($fields[$field]);
        }
        $max_placeholder = 0;
        [$args] = $this->get_query_arguments();
        $placeholders = array_keys($args);
        foreach ($fields as $field => $value) {
            $update_fields[] = $this->connection->escape_field($field) . '=' . $placeholders[$max_placeholder++];
        }
        $query = $comments . 'UPDATE {' . $this->connection->escape_table($this->table) . '} SET ' . implode(', ', $update_fields);
        if (count($this->condition)) {
            $this->condition->compile($this->connection, $this);
            // There is an implicit string cast on $this->condition.
            $query .= "\nWHERE " . $this->condition;
        }
        return $query;
    }
    /**
     * {@inheritdoc}
     */
    public function arguments(): float|int|array
    {
        [$args] = $this->get_query_arguments();
        return $this->condition->arguments() + $args;
    }
    /**
     * Returns the query arguments with placeholders mapped to their values.
     *
     * @return array
     *   An array containing arguments and update values.
     *   Both arguments and update values are associative array where the keys
     *   are the placeholder names and the values are the placeholder values.
     */
    protected function get_query_arguments(): array
    {
        // Expressions take priority over literal fields, so we process those first
        // and remove any literal fields that conflict.
        $fields = $this->fields;
        $update_values = [];
        foreach ($this->expression_fields as $field => $data) {
            if (!empty($data['arguments'])) {
                $update_values += $data['arguments'];
            }
            if ($data['expression'] instanceof Select_Interface) {
                $data['expression']->compile($this->connection, $this);
                $update_values += $data['expression']->arguments();
            }
            unset($fields[$field]);
        }
        // Because we filter $fields the same way here and in __toString(), the
        // placeholders will all match up properly.
        $max_placeholder = 0;
        $args = [];
        foreach ($fields as $value) {
            $args[':db_update_placeholder_' . $max_placeholder++] = $value;
        }
        return [$args, $update_values];
    }
}