<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Entity\Entity_Storage_Interface;
/**
 * Provides an interface for configuration entity storage.
 */
interface Config_Entity_Storage_Interface extends Entity_Storage_Interface
{
    /**
     * Extracts the configuration entity ID from the full configuration name.
     *
     * @param string $config_name
     *   The full configuration name to extract the ID from; for example,
     *   'views.view.archive'.
     * @param string $config_prefix
     *   The config prefix of the configuration entity; for example, 'views.view'.
     *
     * @return string
     *   The ID of the configuration entity.
     */
    public static function get_id_from_config_name($config_name, $config_prefix);
    /**
     * Creates a configuration entity from storage values.
     *
     * Allows the configuration entity storage to massage storage values before
     * creating an entity.
     *
     * @param array $values
     *   The array of values from the configuration storage.
     *
     * @return ConfigEntityInterface
     *   The configuration entity.
     *
     * @see \Drupal\Core\Entity\EntityStorageBase::mapFromStorageRecords()
     * @see \Drupal\field\FieldStorageConfigStorage::mapFromStorageRecords()
     */
    public function create_from_storage_record(array $values);
    /**
     * Updates a configuration entity from storage values.
     *
     * Allows the configuration entity storage to massage storage values before
     * updating an entity.
     *
     * @param ConfigEntityInterface $entity
     *   The configuration entity to update.
     * @param array $values
     *   The array of values from the configuration storage.
     *
     * @return ConfigEntityInterface
     *   The configuration entity.
     *
     * @see \Drupal\Core\Entity\EntityStorageBase::mapFromStorageRecords()
     * @see \Drupal\field\FieldStorageConfigStorage::mapFromStorageRecords()
     */
    public function update_from_storage_record(Config_Entity_Interface $entity, array $values);
    /**
     * Loads one entity in their original form without overrides.
     *
     * @param mixed $id
     *   The ID of the entity to load.
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     *   An entity object. NULL if no matching entity is found.
     */
    public function load_override_free($id);
    /**
     * Loads one or more entities in their original form without overrides.
     *
     * @param string[]|null $ids
     *   An array of entity IDs, or NULL to load all entities.
     *
     * @return \Drupal\Core\Entity\EntityInterface[]
     *   An array of entity objects indexed by their IDs. Returns an empty array
     *   if no matching entities are found.
     */
    public function load_multiple_override_free(?array $ids = null);
}