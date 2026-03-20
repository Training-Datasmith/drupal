<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Config\Config_Name_Exception;
use Drupal\Core\Entity\Entity_Storage_Interface;
/**
 * A base class for config entity types that act as bundles.
 *
 * Entity types that want to use this base class must use bundle_of in their
 * annotation to specify for which entity type they are providing bundles for.
 */
abstract class Config_Entity_Bundle_Base extends Config_Entity_Base
{
    /**
     * Deletes display if a bundle is deleted.
     */
    protected function delete_displays()
    {
        // Remove entity displays of the deleted bundle.
        if ($displays = $this->load_displays('entity_view_display')) {
            $storage = $this->entity_type_manager()->get_storage('entity_view_display');
            $storage->delete($displays);
        }
        // Remove entity form displays of the deleted bundle.
        if ($displays = $this->load_displays('entity_form_display')) {
            $storage = $this->entity_type_manager()->get_storage('entity_form_display');
            $storage->delete($displays);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function post_save(Entity_Storage_Interface $storage, $update = true): void
    {
        parent::post_save($storage, $update);
        $entity_type_manager = $this->entity_type_manager();
        $bundle_of = $this->get_entity_type()->get_bundle_of();
        if (!$update) {
            \Drupal::service('entity_bundle.listener')->on_bundle_create($this->id(), $bundle_of);
        } else {
            // Invalidate the render cache of entities for which this entity
            // is a bundle.
            if ($entity_type_manager->has_handler($bundle_of, 'view_builder')) {
                $entity_type_manager->get_view_builder($bundle_of)->reset_cache();
            }
            // Entity bundle field definitions may depend on bundle settings.
            \Drupal::service('entity_field.manager')->clear_cached_field_definitions();
            $this->entity_type_bundle_info()->clear_cached_bundles();
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function post_delete(Entity_Storage_Interface $storage, array $entities): void
    {
        parent::post_delete($storage, $entities);
        foreach ($entities as $entity) {
            $entity->delete_displays();
            \Drupal::service('entity_bundle.listener')->on_bundle_delete($entity->id(), $entity->get_entity_type()->get_bundle_of());
        }
    }
    /**
     * Acts on an entity before the presave hook is invoked.
     *
     * Used before the entity is saved and before invoking the presave hook.
     *
     * Ensure that config entities which are bundles of other entities cannot have
     * their ID changed.
     *
     * @param \Drupal\Core\Entity\EntityStorageInterface $storage
     *   The entity storage object.
     *
     * @throws \Drupal\Core\Config\ConfigNameException
     *   Thrown when attempting to rename a bundle entity.
     */
    public function pre_save(Entity_Storage_Interface $storage): void
    {
        parent::pre_save($storage);
        // Only handle renames, not creations.
        if (!$this->is_new() && $this->get_original_id() !== $this->id()) {
            $bundle_type = $this->get_entity_type();
            $bundle_of = $bundle_type->get_bundle_of();
            if (!empty($bundle_of)) {
                throw new Config_Name_Exception("The machine name of the '{$bundle_type->get_label()}' bundle cannot be changed.");
            }
        }
    }
    /**
     * Returns view or form displays for this bundle.
     *
     * @param string $entity_type_id
     *   The entity type ID of the display type to load.
     *
     * @return \Drupal\Core\Entity\Display\EntityDisplayInterface[]
     *   A list of matching displays.
     */
    protected function load_displays($entity_type_id)
    {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
        $storage = $this->entity_type_manager()->get_storage($entity_type_id);
        $ids = $storage->get_query()->condition('id', $this->get_entity_type()->get_bundle_of() . '.' . $this->get_original_id() . '.', 'STARTS_WITH')->execute();
        if ($ids) {
            $storage = $this->entity_type_manager()->get_storage($entity_type_id);
            return $storage->load_multiple($ids);
        }
        return [];
    }
}