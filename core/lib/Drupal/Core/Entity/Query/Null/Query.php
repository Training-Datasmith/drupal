<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Query\Null;

use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\Sql\ConditionAggregate;

/**
 * Defines the entity query for configuration entities.
 */
class Query extends QueryBase implements QueryInterface, QueryAggregateInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(): int|array
    {
        if ($this->count) {
            return 0;
        }
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function existsAggregate($field, $function, $langcode = null)
    {
        return $this->conditionAggregate->exists($field, $function, $langcode);
    }

    /**
     * {@inheritdoc}
     */
    public function notExistsAggregate($field, $function, $langcode = null)
    {
        return $this->conditionAggregate->notExists($field, $function, $langcode);
    }

    /**
     * {@inheritdoc}
     */
    public function conditionAggregateGroupFactory($conjunction = 'AND'): \Drupal\Core\Entity\Query\Sql\ConditionAggregate
    {
        return new ConditionAggregate($conjunction, $this);
    }

}
