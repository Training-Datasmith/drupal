<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines a null entity storage.
 *
 * Used for content entity types that have no storage.
 */
class ContentEntityNullStorage extends ContentEntityStorageBase
{
    /**
     * {@inheritdoc}
     */
    public function loadMultiple(?array $ids = null): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoadMultiple(?array $ids = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function load($id): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function loadRevision($revision_id): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMultipleRevisions(array $revision_ids): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRevision($revision_id)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function loadByProperties(array $values = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(array $entities)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($entities)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function save(EntityInterface $entity)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function getQueryServiceName(): string
    {
        return 'entity.query.null';
    }

    /**
     * {@inheritdoc}
     */
    protected function doLoadMultipleRevisionsFieldItems($revision_ids): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function doDeleteFieldItems($entities)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, EntityInterface $entity)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function has($id, EntityInterface $entity)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function countFieldData($storage_definition, $as_bool = false): false|int
    {
        return $as_bool ? false : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function hasData(): bool
    {
        return false;
    }

}
