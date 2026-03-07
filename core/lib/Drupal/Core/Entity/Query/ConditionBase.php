<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Query;

/**
 * Defines a common base class for all entity condition implementations.
 */
abstract class ConditionBase extends ConditionFundamentals implements ConditionInterface
{
    /**
     * {@inheritdoc}
     */
    public function condition($field, $value = null, $operator = null, $langcode = null)
    {
        $this->conditions[] = [
          'field' => $field,
          'value' => $value,
          'operator' => $operator,
          'langcode' => $langcode,
        ];

        return $this;
    }

}
