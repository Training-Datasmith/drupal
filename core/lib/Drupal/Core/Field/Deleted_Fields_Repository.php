<?php

declare(strict_types=1);

namespace Drupal\Core\Field;

/**
 * Provides a repository for deleted field and field storage objects.
 *
 * @internal
 */
class DeletedFieldsRepository implements DeletedFieldsRepositoryInterface
{
    /**
     * Constructs a new deleted fields repository.
     *
     * @param \Drupal\Core\State\StateInterface $state
     *   The state key/value store.
     */
    public function __construct(protected \Drupal\Core\State\StateInterface $state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions($field_storage_unique_id = null)
    {
        $deleted_field_definitions = $this->state->get('field.field.deleted', []);

        if ($field_storage_unique_id) {
            return array_filter($deleted_field_definitions, fn (FieldDefinitionInterface $field_definition) => $field_definition->getFieldStorageDefinition()->getUniqueStorageIdentifier() === $field_storage_unique_id);
        }

        return $deleted_field_definitions;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldStorageDefinitions()
    {
        return $this->state->get('field.storage.deleted', []);
    }

    /**
     * {@inheritdoc}
     */
    public function addFieldDefinition(FieldDefinitionInterface $field_definition): static
    {
        $deleted_field_definitions = $this->state->get('field.field.deleted', []);
        $deleted_field_definitions[$field_definition->getUniqueIdentifier()] = $field_definition;
        $this->state->set('field.field.deleted', $deleted_field_definitions);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addFieldStorageDefinition(FieldStorageDefinitionInterface $field_storage_definition): static
    {
        $deleted_storage_definitions = $this->state->get('field.storage.deleted', []);
        $deleted_storage_definitions[$field_storage_definition->getUniqueStorageIdentifier()] = $field_storage_definition;
        $this->state->set('field.storage.deleted', $deleted_storage_definitions);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeFieldDefinition(FieldDefinitionInterface $field_definition): static
    {
        $deleted_field_definitions = $this->state->get('field.field.deleted', []);
        unset($deleted_field_definitions[$field_definition->getUniqueIdentifier()]);
        $this->state->set('field.field.deleted', $deleted_field_definitions);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeFieldStorageDefinition(FieldStorageDefinitionInterface $field_storage_definition): static
    {
        $deleted_storage_definitions = $this->state->get('field.storage.deleted', []);
        unset($deleted_storage_definitions[$field_storage_definition->getUniqueStorageIdentifier()]);
        $this->state->set('field.storage.deleted', $deleted_storage_definitions);

        return $this;
    }

}
