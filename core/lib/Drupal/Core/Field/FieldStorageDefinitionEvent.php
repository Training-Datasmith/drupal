<?php

declare(strict_types=1);

namespace Drupal\Core\Field;

use Drupal\Component\EventDispatcher\Event;

/**
 * Defines a base class for all field storage definition events.
 */
class FieldStorageDefinitionEvent extends Event
{
    /**
     * Constructs a new FieldStorageDefinitionEvent.
     *
     * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldStorageDefinition
     *   The field storage definition.
     * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $original
     *   (optional) The original field storage definition. This should be passed
     *   only when updating the storage definition.
     */
    public function __construct(protected \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldStorageDefinition, protected ?\Drupal\Core\Field\FieldStorageDefinitionInterface $original = null)
    {
    }

    /**
     * The field storage definition.
     *
     * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
     *   The field storage definition for the entity.
     */
    public function getFieldStorageDefinition()
    {
        return $this->fieldStorageDefinition;
    }

    /**
     * The original field storage definition.
     *
     * @return \Drupal\Core\Field\FieldStorageDefinitionInterface
     *   The field storage definition for the original entity.
     */
    public function getOriginal()
    {
        return $this->original;
    }

}
