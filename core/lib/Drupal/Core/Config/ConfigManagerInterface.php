<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Provides an interface for configuration manager.
 */
interface Config_Manager_Interface
{
    /**
     * Returns the entity type of a configuration object.
     *
     * @param string $name
     *   The configuration object name.
     *
     * @return string|null
     *   Either the entity type name, or NULL if none match.
     */
    public function get_entity_type_id_by_name($name);
    /**
     * Loads a configuration entity using the configuration name.
     *
     * @param string $name
     *   The configuration object name.
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     *   The configuration entity or NULL if it does not exist.
     */
    public function load_config_entity_by_name($name);
    /**
     * Gets the entity type manager.
     *
     * @return \Drupal\Core\Entity\EntityTypeManagerInterface
     *   The entity type manager.
     */
    public function get_entity_type_manager();
    /**
     * Gets the config factory.
     *
     * @return \Drupal\Core\Config\ConfigFactoryInterface
     *   The config factory.
     */
    public function get_config_factory();
    /**
     * Creates a Diff object using the config data from the two storages.
     *
     * @param \Drupal\Core\Config\StorageInterface $source_storage
     *   The storage to diff configuration from.
     * @param \Drupal\Core\Config\StorageInterface $target_storage
     *   The storage to diff configuration to.
     * @param string $source_name
     *   The name of the configuration object in the source storage to diff.
     * @param string $target_name
     *   (optional) The name of the configuration object in the target storage.
     *   If omitted, the source name is used.
     * @param string $collection
     *   (optional) The configuration collection name. Defaults to the default
     *   collection.
     *
     * @return \Drupal\Component\Diff\Diff
     *   A Diff object using the config data from the two storages.
     *
     * @todo Make renderer injectable
     *
     * @see \Drupal\Core\Diff\DiffFormatter
     */
    public function diff(Storage_Interface $source_storage, Storage_Interface $target_storage, $source_name, $target_name = null, $collection = Storage_Interface::DEFAULT_COLLECTION);
    /**
     * Creates a configuration snapshot following a successful import.
     *
     * @param \Drupal\Core\Config\StorageInterface $source_storage
     *   The storage to synchronize configuration from.
     * @param \Drupal\Core\Config\StorageInterface $snapshot_storage
     *   The storage to synchronize configuration to.
     */
    public function create_snapshot(Storage_Interface $source_storage, Storage_Interface $snapshot_storage);
    /**
     * Uninstalls the configuration of a given extension.
     *
     * @param string $type
     *   The extension type; e.g., 'module' or 'theme'.
     * @param string $name
     *   The name of the module or theme to install configuration for.
     */
    public function uninstall($type, $name);
    /**
     * Creates and populates a ConfigDependencyManager object.
     *
     * The configuration dependency manager is populated with data from the active
     * store.
     *
     * @return \Drupal\Core\Config\Entity\ConfigDependencyManager
     *   The configuration dependency manager.
     */
    public function get_config_dependency_manager();
    /**
     * Finds config entities that are dependent on extensions or entities.
     *
     * @param string $type
     *   The type of dependency being checked. Either 'module', 'theme', 'config'
     *   or 'content'.
     * @param array $names
     *   The specific names to check. If $type equals 'module' or 'theme' then it
     *   should be a list of module names or theme names. In the case of 'config'
     *   or 'content' it should be a list of configuration dependency names.
     *
     * @return \Drupal\Core\Config\Entity\ConfigEntityDependency[]
     *   An array of configuration entity dependency objects.
     */
    public function find_config_entity_dependencies($type, array $names);
    /**
     * Finds config entities that are dependent on extensions or entities.
     *
     * @param string $type
     *   The type of dependency being checked. Either 'module', 'theme', 'config'
     *   or 'content'.
     * @param array $names
     *   The specific names to check. If $type equals 'module' or 'theme' then it
     *   should be a list of module names or theme names. In the case of 'config'
     *   or 'content' it should be a list of configuration dependency names.
     *
     * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
     *   An array of dependencies as configuration entities.
     */
    public function find_config_entity_dependencies_as_entities($type, array $names);
    /**
     * Lists config entities to update and delete on removal of a dependency.
     *
     * @param string $type
     *   The type of dependency being checked. Either 'module', 'theme', 'config'
     *   or 'content'.
     * @param array $names
     *   The specific names to check. If $type equals 'module' or 'theme' then it
     *   should be a list of module names or theme names. In the case of 'config'
     *   or 'content' it should be a list of configuration dependency names.
     * @param bool $dry_run
     *   If set to FALSE the entities returned in the list of updates will be
     *   modified. In order to make the changes the caller needs to save them. If
     *   set to TRUE the entities returned will not be modified.
     *
     * @return array
     *   An array with the keys: 'update', 'delete' and 'unchanged'. The value of
     *   each is a list of configuration entities that need to have that action
     *   applied when the supplied dependencies are removed. Updates need to be
     *   processed before deletes. The order of the deletes is significant and
     *   must be processed in the returned order.
     */
    public function get_config_entities_to_change_on_dependency_removal($type, array $names, $dry_run = true);
    /**
     * Gets available collection information using the event system.
     *
     * @return \Drupal\Core\Config\ConfigCollectionInfo
     *   The object which contains information about the available collections.
     */
    public function get_config_collection_info();
    /**
     * Finds missing content dependencies declared in configuration entities.
     *
     * @return array
     *   A list of missing content dependencies. The array is keyed by UUID. Each
     *   value is an array with the following keys: 'entity_type', 'bundle' and
     *   'uuid'.
     */
    public function find_missing_content_dependencies();
}