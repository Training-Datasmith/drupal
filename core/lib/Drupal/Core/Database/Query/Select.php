<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * Query builder for SELECT statements.
 *
 * @ingroup database
 */
class Select extends Query implements Select_Interface
{
    use Query_Condition_Trait;
    /**
     * The fields to SELECT.
     *
     * @var array
     */
    protected $fields = [];
    /**
     * The expressions to SELECT as virtual fields.
     *
     * @var array
     */
    protected $expressions = [];
    /**
     * The tables against which to JOIN.
     *
     * @var array
     * This property is a nested array. Each entry is an array representing
     * a single table against which to join. The structure of each entry is:
     *
     * @code
     * [
     *   'type' => $join_type (one of INNER, LEFT OUTER, RIGHT OUTER),
     *   'table' => $table,
     *   'alias' => $alias_of_the_table,
     *   'condition' => $join_condition (string or Condition object),
     *   'arguments' => $array_of_arguments_for_placeholders_in_the condition.
     *   'all_fields' => TRUE to SELECT $alias.*, FALSE or NULL otherwise.
     * ]
     * @endcode
     * If $table is a string, it is taken as the name of a table. If it is
     * a Select query object, it is taken as a subquery.
     *
     * If $join_condition is a Condition object, any arguments should be
     * incorporated into the object; a separate array of arguments does not
     * need to be provided.
     */
    protected $tables = [];
    /**
     * The fields by which to order this query.
     *
     * This is an associative array. The keys are the fields to order, and the
     * value is the direction to order, either ASC or DESC.
     *
     * @var array
     */
    protected $order = [];
    /**
     * The fields by which to group.
     *
     * @var array
     */
    protected $group = [];
    /**
     * The conditional object for the HAVING clause.
     *
     * @var \Drupal\Core\Database\Query\Condition
     */
    protected $having;
    /**
     * Whether or not this query should be DISTINCT.
     *
     * @var bool
     */
    protected $distinct = false;
    /**
     * The range limiters for this query.
     *
     * @var array
     */
    protected $range;
    /**
     * An array whose elements specify a query to UNION, and the UNION type.
     *
     * The 'type' key may be '', 'ALL', or 'DISTINCT' to represent a 'UNION',
     * 'UNION ALL', or 'UNION DISTINCT' statement, respectively.
     *
     * All entries in this array will be applied from front to back, with the
     * first query to union on the right of the original query, the second union
     * to the right of the first, etc.
     *
     * @var array
     */
    protected $union = [];
    /**
     * Indicates if preExecute() has already been called.
     *
     * @var bool
     */
    protected $prepared = false;
    /**
     * The FOR UPDATE status.
     *
     * @var bool
     */
    protected $for_update = false;
    /**
     * The query metadata for alter purposes.
     */
    public array $alter_meta_data;
    /**
     * The query tags.
     */
    public array $alter_tags;
    /**
     * Constructs a Select object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   Database connection object.
     * @param string|\Drupal\Core\Database\Query\SelectInterface $table
     *   The table name or subquery that is being queried.
     * @param string $alias
     *   The alias for the table.
     * @param array $options
     *   Array of query options.
     */
    public function __construct(Connection $connection, $table, $alias = null, $options = [])
    {
        parent::__construct($connection, $options);
        $conjunction = $options['conjunction'] ?? 'AND';
        $this->condition = $this->connection->condition($conjunction);
        $this->having = $this->connection->condition($conjunction);
        $this->add_join(null, $table, $alias);
    }
    /**
     * {@inheritdoc}
     */
    public function add_tag($tag): static
    {
        $this->alter_tags[$tag] = 1;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function has_tag($tag): bool
    {
        return isset($this->alter_tags[$tag]);
    }
    /**
     * {@inheritdoc}
     */
    public function has_all_tags(string ...$tags): bool
    {
        return !(bool) array_diff($tags, array_keys($this->alter_tags));
    }
    /**
     * {@inheritdoc}
     */
    public function has_any_tag(string ...$tags): bool
    {
        return (bool) array_intersect($tags, array_keys($this->alter_tags));
    }
    /**
     * {@inheritdoc}
     */
    public function add_meta_data($key, $object): static
    {
        $this->alter_meta_data[$key] = $object;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_meta_data($key)
    {
        return $this->alter_meta_data[$key] ?? null;
    }
    /**
     * {@inheritdoc}
     */
    public function arguments(): null|float|int|array
    {
        if (!$this->compiled()) {
            return null;
        }
        $args = $this->condition->arguments() + $this->having->arguments();
        foreach ($this->tables as $table) {
            if ($table['arguments']) {
                $args += $table['arguments'];
            }
            // If this table is a subquery, grab its arguments recursively.
            if ($table['table'] instanceof Select_Interface) {
                $args += $table['table']->arguments();
            }
            // If the join condition is an object, grab its arguments recursively.
            if (!empty($table['condition']) && $table['condition'] instanceof Condition_Interface) {
                $args += $table['condition']->arguments();
            }
        }
        foreach ($this->expressions as $expression) {
            if ($expression['arguments']) {
                $args += $expression['arguments'];
            }
        }
        // If there are any dependent queries to UNION,
        // incorporate their arguments recursively.
        foreach ($this->union as $union) {
            $args += $union['query']->arguments();
        }
        return $args;
    }
    /**
     * {@inheritdoc}
     */
    public function compile(Connection $connection, Placeholder_Interface $query_placeholder): void
    {
        $this->condition->compile($connection, $query_placeholder);
        $this->having->compile($connection, $query_placeholder);
        foreach ($this->tables as $table) {
            // If this table is a subquery, compile it recursively.
            if ($table['table'] instanceof Select_Interface) {
                $table['table']->compile($connection, $query_placeholder);
            }
            // Make sure join conditions are also compiled.
            if (!empty($table['condition']) && $table['condition'] instanceof Condition_Interface) {
                $table['condition']->compile($connection, $query_placeholder);
            }
        }
        // If there are any dependent queries to UNION, compile it recursively.
        foreach ($this->union as $union) {
            $union['query']->compile($connection, $query_placeholder);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function compiled(): bool
    {
        if (!$this->condition->compiled() || !$this->having->compiled()) {
            return false;
        }
        foreach ($this->tables as $table) {
            // If this table is a subquery, check its status recursively.
            if ($table['table'] instanceof Select_Interface) {
                if (!$table['table']->compiled()) {
                    return false;
                }
            }
            if (!empty($table['condition']) && $table['condition'] instanceof Condition_Interface) {
                if (!$table['condition']->compiled()) {
                    return false;
                }
            }
        }
        foreach ($this->union as $union) {
            if (!$union['query']->compiled()) {
                return false;
            }
        }
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function having_condition($field, $value = null, $operator = null): static
    {
        $this->having->condition($field, $value, $operator);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function &having_conditions()
    {
        return $this->having->conditions();
    }
    /**
     * {@inheritdoc}
     */
    public function having_arguments()
    {
        return $this->having->arguments();
    }
    /**
     * {@inheritdoc}
     */
    public function having($snippet, $args = []): static
    {
        $this->having->where($snippet, $args);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_compile(Connection $connection): void
    {
        $this->having->compile($connection, $this);
    }
    /**
     * {@inheritdoc}
     */
    public function extend($extender_name)
    {
        $parts = explode('\\', $extender_name);
        $class = end($parts);
        $driver_class = $this->connection->get_driver_class($class);
        if ($driver_class !== $class) {
            return new $driver_class($this, $this->connection);
        }
        return new $extender_name($this, $this->connection);
    }
    /**
     * {@inheritdoc}
     */
    public function having_is_null($field): static
    {
        $this->having->is_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_is_not_null($field): static
    {
        $this->having->is_not_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_exists(Select_Interface $select): static
    {
        $this->having->exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function having_not_exists(Select_Interface $select): static
    {
        $this->having->not_exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function for_update($set = true): static
    {
        if (isset($set)) {
            $this->for_update = $set;
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function &get_fields()
    {
        return $this->fields;
    }
    /**
     * {@inheritdoc}
     */
    public function &get_expressions()
    {
        return $this->expressions;
    }
    /**
     * {@inheritdoc}
     */
    public function &get_order_by()
    {
        return $this->order;
    }
    /**
     * {@inheritdoc}
     */
    public function &get_group_by()
    {
        return $this->group;
    }
    /**
     * {@inheritdoc}
     */
    public function &get_tables()
    {
        return $this->tables;
    }
    /**
     * {@inheritdoc}
     */
    public function &get_union()
    {
        return $this->union;
    }
    /**
     * {@inheritdoc}
     */
    public function escape_like($string)
    {
        return $this->connection->escape_like($string);
    }
    /**
     * {@inheritdoc}
     */
    public function escape_field($string)
    {
        return $this->connection->escape_field($string);
    }
    /**
     * {@inheritdoc}
     */
    public function get_arguments(?Placeholder_Interface $query_placeholder = null)
    {
        if (!isset($query_placeholder)) {
            $query_placeholder = $this;
        }
        $this->compile($this->connection, $query_placeholder);
        return $this->arguments();
    }
    /**
     * {@inheritdoc}
     */
    public function is_prepared()
    {
        return $this->prepared;
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
        // Only execute this once.
        if ($query->is_prepared()) {
            return true;
        }
        // Modules may alter all queries or only those having a particular tag.
        if (isset($this->alter_tags)) {
            // Many contrib modules as well as Entity Reference in core assume that
            // query tags used for access-checking purposes follow the pattern
            // $entity_type . '_access'. But this is not the case for taxonomy terms,
            // since the core Taxonomy module used to add term_access instead of
            // taxonomy_term_access to its queries. Provide backwards compatibility
            // by adding both tags here instead of attempting to fix all contrib
            // modules in a coordinated effort.
            // @todo Extract this mechanism into a hook as part of a public
            //   (non-security) issue.
            // @todo Emit E_USER_DEPRECATED if term_access is used.
            //   https://www.drupal.org/node/2575081
            $term_access_tags = ['term_access' => 1, 'taxonomy_term_access' => 1];
            if (array_intersect_key($this->alter_tags, $term_access_tags)) {
                $this->alter_tags += $term_access_tags;
            }
            $hooks = ['query'];
            foreach ($this->alter_tags as $tag => $value) {
                $hooks[] = 'query_' . $tag;
            }
            \Drupal::module_handler()->alter($hooks, $query);
        }
        $this->prepared = true;
        // Now also prepare any sub-queries.
        foreach ($this->tables as $table) {
            if ($table['table'] instanceof Select_Interface) {
                $table['table']->pre_execute();
            }
        }
        foreach ($this->union as $union) {
            $union['query']->pre_execute();
        }
        return $this->prepared;
    }
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // If validation fails, simply return NULL. Note that validation routines in
        // preExecute() may throw exceptions instead.
        if (!$this->pre_execute()) {
            return null;
        }
        $args = $this->get_arguments();
        return $this->connection->query((string) $this, $args, $this->query_options);
    }
    /**
     * {@inheritdoc}
     */
    public function distinct($distinct = true): static
    {
        $this->distinct = $distinct;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function add_field($table_alias, $field, $alias = null)
    {
        // If no alias is specified, first try the field name itself.
        if (empty($alias)) {
            $alias = $field;
        }
        // If that's already in use, try the table name and field name.
        if (!empty($this->fields[$alias])) {
            $alias = $table_alias . '_' . $field;
        }
        // If that is already used, just add a counter until we find an unused
        // alias.
        $alias_candidate = $alias;
        $count = 2;
        while (!empty($this->fields[$alias_candidate])) {
            $alias_candidate = $alias . '_' . $count++;
        }
        $alias = $alias_candidate;
        $this->fields[$alias] = ['field' => $field, 'table' => $table_alias, 'alias' => $alias];
        return $alias;
    }
    /**
     * {@inheritdoc}
     */
    public function fields($table_alias, array $fields = []): static
    {
        if ($fields) {
            foreach ($fields as $field) {
                // We don't care what alias was assigned.
                $this->add_field($table_alias, $field);
            }
        } else {
            // We want all fields from this table.
            $this->tables[$table_alias]['all_fields'] = true;
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function add_expression($expression, $alias = null, $arguments = [])
    {
        if (empty($alias)) {
            $alias = 'expression';
        }
        $alias_candidate = $alias;
        $count = 2;
        while (!empty($this->expressions[$alias_candidate])) {
            $alias_candidate = $alias . '_' . $count++;
        }
        $alias = $alias_candidate;
        $this->expressions[$alias] = ['expression' => $expression, 'alias' => $alias, 'arguments' => $arguments];
        return $alias;
    }
    /**
     * {@inheritdoc}
     */
    public function join($table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->add_join('INNER', $table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function inner_join($table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->add_join('INNER', $table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function left_join($table, $alias = null, $condition = null, $arguments = [])
    {
        return $this->add_join('LEFT OUTER', $table, $alias, $condition, $arguments);
    }
    /**
     * {@inheritdoc}
     */
    public function add_join($type, $table, $alias = null, $condition = null, $arguments = [])
    {
        if (empty($alias)) {
            if ($table instanceof Select_Interface) {
                $alias = 'subquery';
            } else {
                $alias = $table;
            }
        }
        $alias_candidate = $alias;
        $count = 2;
        while (!empty($this->tables[$alias_candidate])) {
            $alias_candidate = $alias . '_' . $count++;
        }
        $alias = $alias_candidate;
        if (is_string($condition)) {
            $condition = str_replace('%alias', $alias, $condition);
        }
        $this->tables[$alias] = ['join type' => $type, 'table' => $table, 'alias' => $alias, 'condition' => $condition, 'arguments' => $arguments];
        return $alias;
    }
    /**
     * {@inheritdoc}
     */
    public function order_by($field, $direction = 'ASC'): static
    {
        // Only allow ASC and DESC, default to ASC.
        $direction = strtoupper($direction) == 'DESC' ? 'DESC' : 'ASC';
        $this->order[$field] = $direction;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function order_random(): static
    {
        $alias = $this->add_expression('RAND()', 'random_field');
        $this->order_by($alias);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function range($start = null, $length = null): static
    {
        $this->range = $start !== null ? ['start' => $start, 'length' => $length] : [];
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function union(Select_Interface $query, $type = ''): static
    {
        // Handle UNION aliasing.
        switch ($type) {
            // Fold UNION DISTINCT to UNION for better cross database support.
            case 'DISTINCT':
            case '':
                $type = 'UNION';
                break;
            case 'ALL':
                $type = 'UNION ALL';
            // no break
            default:
        }
        $this->union[] = ['type' => $type, 'query' => $query];
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function group_by($field): static
    {
        $this->group[$field] = $field;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function count_query()
    {
        $count = $this->prepare_count_query();
        $query = $this->connection->select($count, null, $this->query_options);
        $query->add_expression('COUNT(*)');
        return $query;
    }
    /**
     * Prepares a count query from the current query object.
     *
     * @return \Drupal\Core\Database\Query\Select
     *   A new query object ready to have COUNT(*) performed on it.
     */
    protected function prepare_count_query(): static
    {
        // Create our new query object that we will mutate into a count query.
        $count = clone $this;
        $group_by = $count->get_group_by();
        $having = $count->having_conditions();
        if (!$count->distinct && !isset($having[0])) {
            // When not executing a distinct query, we can zero-out existing fields
            // and expressions that are not used by a GROUP BY or HAVING. Fields
            // listed in a GROUP BY or HAVING clause need to be present in the
            // query.
            $fields =& $count->get_fields();
            foreach (array_keys($fields) as $field) {
                if (empty($group_by[$field])) {
                    unset($fields[$field]);
                }
            }
            $expressions =& $count->get_expressions();
            foreach (array_keys($expressions) as $field) {
                if (empty($group_by[$field])) {
                    unset($expressions[$field]);
                }
            }
            // Also remove 'all_fields' statements, which are expanded into
            // tablename.* when the query is executed.
            foreach ($count->tables as &$table) {
                unset($table['all_fields']);
            }
        }
        // If we've just removed all fields from the query, make sure there is at
        // least one so that the query still runs.
        $count->add_expression('1');
        // Ordering a count query is a waste of cycles, and breaks on some
        // databases anyway.
        $orders =& $count->get_order_by();
        $orders = [];
        if ($count->distinct && !empty($group_by)) {
            // If the query is distinct and contains a GROUP BY, we need to remove the
            // distinct because SQL99 does not support counting on distinct multiple
            // fields.
            $count->distinct = false;
        }
        // If there are any dependent queries to UNION, prepare each of those for
        // the count query also.
        foreach ($count->union as &$union) {
            $union['query'] = $union['query']->prepare_count_query();
        }
        return $count;
    }
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        // For convenience, we compile the query ourselves if the caller forgot
        // to do it. This allows constructs like "(string) $query" to work. When
        // the query will be executed, it will be recompiled using the proper
        // placeholder generator anyway.
        if (!$this->compiled()) {
            $this->compile($this->connection, $this);
        }
        // Create a sanitized comment string to prepend to the query.
        $comments = $this->connection->make_comment($this->comments);
        // SELECT.
        $query = $comments . 'SELECT ';
        if ($this->distinct) {
            $query .= 'DISTINCT ';
        }
        // FIELDS and EXPRESSIONS.
        $fields = [];
        foreach ($this->tables as $alias => $table) {
            if (!empty($table['all_fields'])) {
                $fields[] = $this->connection->escape_alias($alias) . '.*';
            }
        }
        foreach ($this->fields as $field) {
            // Note that $field['table'] holds the table_alias.
            // @see \Drupal\Core\Database\Query\Select::addField
            $table = isset($field['table']) ? $field['table'] . '.' : '';
            // Always use the AS keyword for field aliases, as some
            // databases require it (e.g., PostgreSQL).
            $fields[] = $this->connection->escape_field($table . $field['field']) . ' AS ' . $this->connection->escape_alias($field['alias']);
        }
        foreach ($this->expressions as $expression) {
            $fields[] = $expression['expression'] . ' AS ' . $this->connection->escape_alias($expression['alias']);
        }
        $query .= implode(', ', $fields);
        // FROM - We presume all queries have a FROM, as any query that doesn't
        // won't need the query builder anyway.
        $query .= "\nFROM";
        foreach ($this->tables as $table) {
            $query .= "\n";
            if (isset($table['join type'])) {
                $query .= $table['join type'] . ' JOIN ';
            }
            // If the table is a subquery, compile it and integrate it into this
            // query.
            if ($table['table'] instanceof Select_Interface) {
                // Run preparation steps on this sub-query before converting to string.
                $subquery = $table['table'];
                $subquery->pre_execute();
                $table_string = '(' . $subquery . ')';
            } else {
                $table_string = $this->connection->escape_table($table['table']);
                // Do not attempt prefixing cross database / schema queries.
                if (!str_contains($table_string, '.')) {
                    $table_string = '{' . $table_string . '}';
                }
            }
            // Don't use the AS keyword for table aliases, as some
            // databases don't support it (e.g., Oracle).
            $query .= $table_string . ' ' . $this->connection->escape_alias($table['alias']);
            if (!empty($table['condition'])) {
                $query .= ' ON ' . $table['condition'];
            }
        }
        // WHERE.
        if (count($this->condition)) {
            // There is an implicit string cast on $this->condition.
            $query .= "\nWHERE " . $this->condition;
        }
        // GROUP BY.
        if ($this->group) {
            $group_by_fields = array_map(fn(string $field): string => $this->connection->escape_field($field), $this->group);
            $query .= "\nGROUP BY " . implode(', ', $group_by_fields);
        }
        // HAVING.
        if (count($this->having)) {
            // There is an implicit string cast on $this->having.
            $query .= "\nHAVING " . $this->having;
        }
        // UNION is a little odd, as the select queries to combine are passed into
        // this query, but syntactically they all end up on the same level.
        if ($this->union) {
            foreach ($this->union as $union) {
                $query .= ' ' . $union['type'] . ' ' . $union['query'];
            }
        }
        // ORDER BY.
        if ($this->order) {
            $query .= "\nORDER BY ";
            $fields = [];
            foreach ($this->order as $field => $direction) {
                $fields[] = $this->connection->escape_field($field) . ' ' . $direction;
            }
            $query .= implode(', ', $fields);
        }
        // RANGE
        // There is no universal SQL standard for handling range or limit clauses.
        // Fortunately, all core-supported databases use the same range syntax.
        // Databases that need a different syntax can override this method and
        // do whatever alternate logic they need to.
        if (!empty($this->range)) {
            $query .= "\nLIMIT " . (int) $this->range['length'] . ' OFFSET ' . (int) $this->range['start'];
        }
        if ($this->for_update) {
            $query .= ' FOR UPDATE';
        }
        return $query;
    }
    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        parent::__clone();
        // On cloning, also clone the dependent objects. However, we do not
        // want to clone the database connection object as that would duplicate the
        // connection itself.
        $this->condition = clone $this->condition;
        $this->having = clone $this->having;
        foreach ($this->union as $key => $aggregate) {
            $this->union[$key]['query'] = clone $aggregate['query'];
        }
        foreach ($this->tables as $alias => $table) {
            if ($table['table'] instanceof Select_Interface) {
                $this->tables[$alias]['table'] = clone $table['table'];
            }
        }
    }
}