<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Query;

/**
 * A common base class for all aggregation entity condition implementations.
 */
abstract class ConditionAggregateBase extends ConditionFundamentals implements ConditionAggregateInterface
{
    /**
     * {@inheritdoc}
     */
    public function condition($field, $function = null, $value = null, $operator = null, $langcode = null)
    {
        $this->conditions[] = [
          'field' => $field,
          'function' => $function,
          'value' => $value,
          'operator' => $operator,
          'langcode' => $langcode,
        ];

        return $this;
    }

}
