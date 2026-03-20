<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * The base extender class for Select queries.
 */
class Select_Extender implements Select_Interface
{
    /**
     * The Select query object we are extending/decorating.
     *
     * @var \Drupal\Core\Database\Query\SelectInterface
     */
    protected $query;
    /**
     * A unique identifier for this query object.
     */
    protected string $unique_identifier;
    /**
     * The placeholder counter.
     *
     * @var int
     */
    protected $placeholder = 0;
    public function __construct(
        Select_Interface $query,
        /**
         * The connection object on which to run this query.
         */
        protected \Drupal\Core\Database\Connection $connection
    )
    {
        $this->unique_identifier = uniqid('', true);
        $this->query = $query;
    }
    /**
     * {@inheritdoc}
     */
    public function unique_identifier()
    {
        return $this->unique_identifier;
    }
    /**
     * {@inheritdoc}
     */
    public function next_placeholder()
    {
        return $this->placeholder++;
    }
    /**
     * {@inheritdoc}
     */
    public function add_tag($tag): static
    {
        $this->query->add_tag($tag);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function has_tag($tag)
    {
        return $this->query->has_tag($tag);
    }
    /**
     * {@inheritdoc}
     */
    public function has_all_tags(string ...$tags): mixed
    {
        return call_user_func_array($this->query->has_all_tags(...), $tags);
    }
    /**
     * {@inheritdoc}
     */
    public function has_any_tag(string ...$tags): mixed
    {
        return call_user_func_array($this->query->has_any_tag(...), $tags);
    }
    /**
     * {@inheritdoc}
     */
    public function add_meta_data($key, $object): static
    {
        $this->query->add_meta_data($key, $object);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_meta_data($key)
    {
        return $this->query->get_meta_data($key);
    }
    /**
     * {@inheritdoc}
     */
    public function condition($field, $value = null, $operator = '='): static
    {
        $this->query->condition($field, $value, $operator);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function &conditions()
    {
        return $this->query->conditions();
    }
    /**
     * {@inheritdoc}
     */
    public function arguments()
    {
        return $this->query->arguments();
    }
    /**
     * {@inheritdoc}
     */
    public function where($snippet, $args = []): static
    {
        $this->query->where($snippet, $args);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function compile(Connection $connection, Placeholder_Interface $query_placeholder)
    {
        return $this->query->compile($connection, $query_placeholder);
    }
    /**
     * {@inheritdoc}
     */
    public function compiled()
    {
        return $this->query->compiled();
    }
    /**
     * {@inheritdoc}
     */
    public function having_condition($field, $value = null, $operator = '='): static
    {
        $this->query->having_condition($field, $value, $operator);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function &having_conditions()
    {
        return $this->query->having_conditions();
    }
    /**
     * {@inheritdoc}
     */
    public function having_arguments()
    {
        return $this->query->having_arguments();
    }
    /**
     * {@inheritdoc}
     */
    public function having($snippet, $args = []): static
    {
        $this->query->having($snippet, $args);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_compile(Connection $connection)
    {
        return $this->query->having_compile($connection);
    }
    /**
     * {@inheritdoc}
     */
    public function having_is_null($field): static
    {
        $this->query->having_is_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_is_not_null($field): static
    {
        $this->query->having_is_not_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_exists(Select_Interface $select): static
    {
        $this->query->having_exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_not_exists(Select_Interface $select): static
    {
        $this->query->having_not_exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function extend($extender_name)
    {
        // We cannot call $this->query->extend(), because with multiple extenders
        // you will replace all the earlier extenders with the last extender,
        // instead of creating list of objects that extend each other.
        $parts = explode('\\', $extender_name);
        $class = end($parts);
        $driver_class = $this->connection->get_driver_class($class);
        if ($driver_class !== $class) {
            return new $driver_class($this, $this->connection);
        }
        return new $extender_name($this, $this->connection);
    }
    /* Alter accessors to expose the query data to alter hooks. */
    /**
     * {@inheritdoc}
     */
    public function &get_fields()
    {
        return $this->query->get_fields();
    }
    /**
     * {@inheritdoc}
     */
    public function &get_expressions()
    {
        return $this->query->get_expressions();
    }
    /**
     * {@inheritdoc}
     */
    public function &get_order_by()
    {
        return $this->query->get_order_by();
    }
    /**
     * {@inheritdoc}
     */
    public function &get_group_by()
    {
        return $this->query->get_group_by();
    }
    /**
     * {@inheritdoc}
     */
    public function &get_tables()
    {
        return $this->query->get_tables();
    }
    /**
     * {@inheritdoc}
     */
    public function &get_union()
    {
        return $this->query->get_union();
    }
    /**
     * {@inheritdoc}
     */
    public function escape_like($string)
    {
        return $this->query->escape_like($string);
    }
    /**
     * {@inheritdoc}
     */
    public function escape_field($string): static
    {
        $this->query->escape_field($string);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_arguments(?Placeholder_Interface $query_placeholder = null)
    {
        return $this->query->get_arguments($query_placeholder);
    }
    /**
     * {@inheritdoc}
     */
    public function is_prepared()
    {
        return $this->query->is_prepared();
    }
    /**
     * {@inheritdoc}
     */
    public function pre_execute(?Select_Interface $query = null)
    {
        // If no query object is passed in, use $this.
        if (!isset($query)) {
            $query = $this;
        }
        return $this->query->pre_execute($query);
    }
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // By calling preExecute() here, we force it to preprocess the extender
        // object rather than just the base query object.  That means
        // hook_query_alter() gets access to the extended object.
        if (!$this->pre_execute($this)) {
            return null;
        }
        return $this->query->execute();
    }
    /**
     * {@inheritdoc}
     */
    public function distinct($distinct = true): static
    {
        $this->query->distinct($distinct);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function add_field($table_alias, $field, $alias = null)
    {
        return $this->query->add_field($table_alias, $field, $alias);
    }
    /**
     * {@inheritdoc}
     */
    public function fields($table_alias, array $fields = []): static
    {
        $this->query->fields($table_alias, $fields);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function add_expression($expression, $alias = null, $arguments = [])
    {
        return $this->query->add_expression($expression, $alias, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function join($table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->query->join($table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function inner_join($table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->query->inner_join($table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function left_join($table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->query->left_join($table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function add_join($type, $table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->query->add_join($type, $table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function order_by($field, $direction = 'ASC'): static
    {
        $this->query->order_by($field, $direction);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function order_random(): static
    {
        $this->query->order_random();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function range($start = null, $length = null): static
    {
        $this->query->range($start, $length);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function union(Select_Interface $query, $type = ''): static
    {
        $this->query->union($query, $type);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function group_by($field): static
    {
        $this->query->group_by($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function for_update($set = true): static
    {
        $this->query->for_update($set);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function count_query()
    {
        return $this->query->count_query();
    }
    /**
     * {@inheritdoc}
     */
    public function is_null($field): static
    {
        $this->query->is_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_not_null($field): static
    {
        $this->query->is_not_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function exists(Select_Interface $select): static
    {
        $this->query->exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function not_exists(Select_Interface $select): static
    {
        $this->query->not_exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function always_false(): static
    {
        $this->query->always_false();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return (string) $this->query;
    }
    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        $this->unique_identifier = uniqid('', true);
        // We need to deep-clone the query we're wrapping, which in turn may
        // deep-clone other objects.  Exciting!
        $this->query = clone $this->query;
    }
    /**
     * Magic override for undefined methods.
     *
     * If one extender extends another extender, then methods in the inner
     * extender will not be exposed on the outer extender.  That's because we
     * cannot know in advance what those methods will be, so we cannot provide
     * wrapping implementations as we do above.  Instead, we use this slower
     * catch-all method to handle any additional methods.
     */
    public function __call(string $method, array $args)
    {
        $return = call_user_func_array([$this->query, $method], $args);
        // Some methods will return the called object as part of a fluent interface.
        // Others will return some useful value.  If it's a value, then the caller
        // probably wants that value.  If it's the called object, then we instead
        // return this object.  That way we don't "lose" an extender layer when
        // chaining methods together.
        if ($return instanceof Select_Interface) {
            return $this;
        }
        return $return;
    }
    /**
     * {@inheritdoc}
     */
    public function condition_group_factory($conjunction = 'AND')
    {
        return $this->connection->condition($conjunction);
    }
    /**
     * {@inheritdoc}
     */
    public function and_condition_group()
    {
        return $this->condition_group_factory('AND');
    }
    /**
     * {@inheritdoc}
     */
    public function or_condition_group()
    {
        return $this->condition_group_factory('OR');
    }
}