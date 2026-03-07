<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Query\Null;

use Drupal\Core\Entity\Query\ConditionBase;

/**
 * Defines the condition class for the null entity query.
 */
class Condition extends ConditionBase
{
    /**
     * {@inheritdoc}
     */
    public function compile($query)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function exists($field, $langcode = null)
    {
        return $this->condition($field, null, 'IS NOT NULL', $langcode);
    }

    /**
     * {@inheritdoc}
     */
    public function notExists($field, $langcode = null)
    {
        return $this->condition($field, null, 'IS NULL', $langcode);
    }

}
