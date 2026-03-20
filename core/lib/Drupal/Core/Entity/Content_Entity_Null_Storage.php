<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Field\Field_Definition_Interface;
/**
 * Defines a null entity storage.
 *
 * Used for content entity types that have no storage.
 */
class Content_Entity_Null_Storage extends Content_Entity_Storage_Base
{
    /**
     * {@inheritdoc}
     */
    public function load_multiple(?array $ids = null): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    protected function do_load_multiple(?array $ids = null)
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
    public function load_revision($revision_id): null
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function load_multiple_revisions(array $revision_ids): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function delete_revision($revision_id)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function load_by_properties(array $values = []): array
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
    protected function do_delete($entities)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function save(Entity_Interface $entity)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function get_query_service_name(): string
    {
        return 'entity.query.null';
    }
    /**
     * {@inheritdoc}
     */
    protected function do_load_multiple_revisions_field_items($revision_ids): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    protected function do_save_field_items(Content_Entity_Interface $entity, array $names = [])
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function do_delete_field_items($entities)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function do_delete_revision_field_items(Content_Entity_Interface $revision)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function read_field_items_to_purge(Field_Definition_Interface $field_definition, $batch_size): array
    {
        return [];
    }
    /**
     * {@inheritdoc}
     */
    protected function purge_field_items(Content_Entity_Interface $entity, Field_Definition_Interface $field_definition)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function do_save($id, Entity_Interface $entity)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function has($id, Entity_Interface $entity)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function count_field_data($storage_definition, $as_bool = false): false|int
    {
        return $as_bool ? false : 0;
    }
    /**
     * {@inheritdoc}
     */
    public function has_data(): bool
    {
        return false;
    }
}