<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
/**
 * Provides an implementation of ConditionInterface.
 *
 * @see \Drupal\Core\Database\Query\ConditionInterface
 */
trait Query_Condition_Trait
{
    /**
     * The condition object for this query.
     *
     * Condition handling is handled via composition.
     *
     * @var \Drupal\Core\Database\Query\Condition
     */
    protected $condition;
    /**
     * {@inheritdoc}
     */
    public function condition($field, $value = null, $operator = '=')
    {
        $this->condition->condition($field, $value, $operator);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_null($field)
    {
        $this->condition->is_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_not_null($field)
    {
        $this->condition->is_not_null($field);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function exists(Select_Interface $select)
    {
        $this->condition->exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function not_exists(Select_Interface $select)
    {
        $this->condition->not_exists($select);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function always_false()
    {
        $this->condition->always_false();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function &conditions()
    {
        return $this->condition->conditions();
    }
    /**
     * {@inheritdoc}
     */
    public function arguments()
    {
        return $this->condition->arguments();
    }
    /**
     * {@inheritdoc}
     */
    public function where($snippet, $args = [])
    {
        $this->condition->where($snippet, $args);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function compile(Connection $connection, Placeholder_Interface $query_placeholder): void
    {
        $this->condition->compile($connection, $query_placeholder);
    }
    /**
     * {@inheritdoc}
     */
    public function compiled()
    {
        return $this->condition->compiled();
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