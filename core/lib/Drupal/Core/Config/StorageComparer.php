<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Cache\Memory_Backend;
use Drupal\Core\Cache\Null_Backend;
use Drupal\Core\Config\Entity\Config_Dependency_Manager;
use Drupal\Core\Dependency_Injection\Dependency_Serialization_Trait;
/**
 * Defines a config storage comparer.
 */
class Storage_Comparer implements Storage_Comparer_Interface
{
    use Dependency_Serialization_Trait {
        __sleep as defaultSleep;
        __wakeup as defaultWakeup;
    }
    /**
     * The source storage used to discover configuration changes.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $source_storage;
    /**
     * The source storages keyed by collection.
     *
     * @var \Drupal\Core\Config\StorageInterface[]
     */
    protected $source_storages;
    /**
     * The target storage used to write configuration changes.
     */
    protected \Drupal\Core\Config\Storage_Interface $target_storage;
    /**
     * The target storages keyed by collection.
     *
     * @var \Drupal\Core\Config\StorageInterface[]
     */
    protected $target_storages;
    /**
     * List of changes to between the source storage and the target storage.
     *
     * The list is keyed by storage collection name.
     *
     * @var array
     */
    protected $changelist;
    /**
     * Sorted list of all the configuration object names in the source storage.
     *
     * The list is keyed by storage collection name.
     *
     * @var array
     */
    protected $source_names = [];
    /**
     * Sorted list of all the configuration object names in the target storage.
     *
     * The list is keyed by storage collection name.
     *
     * @var array
     */
    protected $target_names = [];
    /**
     * A memory cache backend to statically cache source configuration data.
     */
    protected \Drupal\Core\Cache\Null_Backend|\Drupal\Core\Cache\Memory_Backend $source_cache_storage;
    /**
     * A memory cache backend to statically cache target configuration data.
     */
    protected Cache_Backend_Interface $target_cache_storage;
    /**
     * Indicates whether the target storage should be wrapped in a cache.
     *
     * In write mode the StorageComparer no longer wraps the target storage in a
     * static cache. When writing to active configuration, the target storage must
     * reflect any secondary writes to configuration that occur.
     */
    protected bool $write_mode = false;
    /**
     * Constructs the Configuration storage comparer.
     *
     * @param \Drupal\Core\Config\StorageInterface $source_storage
     *   Storage object used to read configuration.
     * @param \Drupal\Core\Config\StorageInterface $target_storage
     *   Storage object used to write configuration.
     */
    public function __construct(Storage_Interface $source_storage, Storage_Interface $target_storage)
    {
        if ($source_storage->get_collection_name() !== Storage_Interface::DEFAULT_COLLECTION) {
            $source_storage = $source_storage->create_collection(Storage_Interface::DEFAULT_COLLECTION);
        }
        if ($target_storage->get_collection_name() !== Storage_Interface::DEFAULT_COLLECTION) {
            $target_storage = $target_storage->create_collection(Storage_Interface::DEFAULT_COLLECTION);
        }
        $time = \Drupal::has_service(Time_Interface::class) ? \Drupal::service(Time_Interface::class) : new Time();
        if ($source_storage instanceof File_Storage) {
            // FileStorage has its own static cache so that multiple reads of the
            // same raw configuration object are not costly.
            $this->source_cache_storage = new Null_Backend('storage_comparer');
            $this->source_storage = $source_storage;
        } else {
            // Wrap the source storage in a static cache so that multiple reads of the
            // same raw configuration object are not costly.
            $this->source_cache_storage = new Memory_Backend($time);
            $this->source_storage = new Cached_Storage($source_storage, $this->source_cache_storage);
        }
        $this->target_cache_storage = new Memory_Backend($time);
        $this->target_storage = $target_storage;
        $this->changelist[Storage_Interface::DEFAULT_COLLECTION] = $this->get_empty_changelist();
    }
    /**
     * {@inheritdoc}
     */
    public function get_source_storage($collection = Storage_Interface::DEFAULT_COLLECTION)
    {
        if (!isset($this->source_storages[$collection])) {
            if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
                $this->source_storages[$collection] = $this->source_storage;
            } else {
                $this->source_storages[$collection] = $this->source_storage->create_collection($collection);
            }
        }
        return $this->source_storages[$collection];
    }
    /**
     * {@inheritdoc}
     */
    public function get_target_storage($collection = Storage_Interface::DEFAULT_COLLECTION)
    {
        if (!isset($this->target_storages[$collection])) {
            $target = $this->target_storage;
            if ($collection !== Storage_Interface::DEFAULT_COLLECTION) {
                $target = $target->create_collection($collection);
            }
            // If we are not in write mode wrap the storage in a static cache so that
            // multiple reads of the same configuration object are cheap.
            if (!$this->write_mode) {
                $target = new Cached_Storage($target, $this->target_cache_storage);
            }
            $this->target_storages[$collection] = $target;
        }
        return $this->target_storages[$collection];
    }
    /**
     * {@inheritdoc}
     */
    public function write_mode(): static
    {
        if (!$this->write_mode) {
            $this->write_mode = true;
            $this->target_cache_storage = new Null_Backend('storage_comparer');
            $this->target_storages = [];
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_empty_changelist(): array
    {
        return ['create' => [], 'update' => [], 'delete' => [], 'rename' => []];
    }
    /**
     * {@inheritdoc}
     */
    public function get_changelist($op = null, $collection = Storage_Interface::DEFAULT_COLLECTION)
    {
        if ($op) {
            return $this->changelist[$collection][$op];
        }
        return $this->changelist[$collection];
    }
    /**
     * Adds changes to the changelist.
     *
     * @param string $collection
     *   The storage collection to add changes for.
     * @param string $op
     *   The change operation performed. Either delete, create, rename, or update.
     * @param array $changes
     *   Array of changes to add to the changelist.
     * @param array|null $sort_order
     *   (optional) Array to sort that can be used to sort the changelist. This
     *   array must contain all the items that are in the change list.
     */
    protected function add_change_list($collection, $op, array $changes, ?array $sort_order = null)
    {
        // Only add changes that aren't already listed.
        $changes = array_diff($changes, $this->changelist[$collection][$op]);
        $this->changelist[$collection][$op] = array_merge($this->changelist[$collection][$op], $changes);
        if (isset($sort_order)) {
            $count = count($this->changelist[$collection][$op]);
            // Sort the changelist in the same order as the $sort_order array and
            // ensure the array is keyed from 0.
            $this->changelist[$collection][$op] = array_values(array_intersect($sort_order, $this->changelist[$collection][$op]));
            if ($count != count($this->changelist[$collection][$op])) {
                throw new \InvalidArgumentException("Sorting the {$op} changelist should not change its length.");
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function create_changelist(): static
    {
        foreach ($this->get_all_collection_names() as $collection) {
            $this->changelist[$collection] = $this->get_empty_changelist();
            $this->get_and_sort_config_data($collection);
            $this->add_changelist_create($collection);
            $this->add_changelist_update($collection);
            $this->add_changelist_delete($collection);
            // Only collections that support configuration entities can have renames.
            if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
                $this->add_changelist_rename($collection);
            }
        }
        return $this;
    }
    /**
     * Creates the delete changelist.
     *
     * The list of deletes is sorted so that dependencies are deleted after
     * configuration entities that depend on them. For example, fields should be
     * deleted after field storages.
     *
     * @param string $collection
     *   The storage collection to operate on.
     */
    protected function add_changelist_delete($collection)
    {
        $deletes = array_diff(array_reverse($this->target_names[$collection]), $this->source_names[$collection]);
        $this->add_change_list($collection, 'delete', $deletes);
    }
    /**
     * Creates the create changelist.
     *
     * The list of creates is sorted so that dependencies are created before
     * configuration entities that depend on them. For example, field storages
     * should be created before fields.
     *
     * @param string $collection
     *   The storage collection to operate on.
     */
    protected function add_changelist_create($collection)
    {
        $creates = array_diff($this->source_names[$collection], $this->target_names[$collection]);
        $this->add_change_list($collection, 'create', $creates);
    }
    /**
     * Creates the update changelist.
     *
     * The list of updates is sorted so that dependencies are created before
     * configuration entities that depend on them. For example, field storages
     * should be updated before fields.
     *
     * @param string $collection
     *   The storage collection to operate on.
     */
    protected function add_changelist_update($collection)
    {
        $recreates = [];
        foreach (array_intersect($this->source_names[$collection], $this->target_names[$collection]) as $name) {
            $source_data = $this->get_source_storage($collection)->read($name);
            $target_data = $this->get_target_storage($collection)->read($name);
            if ($source_data !== $target_data) {
                if (isset($source_data['uuid']) && $source_data['uuid'] !== $target_data['uuid']) {
                    // The entity has the same file as an existing entity but the UUIDs do
                    // not match. This means that the entity has been recreated so config
                    // synchronization should do the same.
                    $recreates[] = $name;
                } else {
                    $this->add_change_list($collection, 'update', [$name]);
                }
            }
        }
        if (!empty($recreates)) {
            // Recreates should become deletes and creates. Deletes should be ordered
            // so that dependencies are deleted first.
            $this->add_change_list($collection, 'create', $recreates, $this->source_names[$collection]);
            $this->add_change_list($collection, 'delete', $recreates, array_reverse($this->target_names[$collection]));
        }
    }
    /**
     * Creates the rename changelist.
     *
     * The list of renames is created from the different source and target names
     * with same UUID. These changes will be removed from the create and delete
     * lists.
     *
     * @param string $collection
     *   The storage collection to operate on.
     */
    protected function add_changelist_rename($collection)
    {
        // Renames will be present in both the create and delete lists.
        $create_list = $this->get_changelist('create', $collection);
        $delete_list = $this->get_changelist('delete', $collection);
        if (empty($create_list) || empty($delete_list)) {
            return;
        }
        $create_uuids = [];
        foreach ($this->source_names[$collection] as $name) {
            $data = $this->get_source_storage($collection)->read($name);
            if (isset($data['uuid']) && in_array($name, $create_list)) {
                $create_uuids[$data['uuid']] = $name;
            }
        }
        if (empty($create_uuids)) {
            return;
        }
        $renames = [];
        // Renames should be ordered so that dependencies are renamed last. This
        // ensures that if there is logic in the configuration entity class to keep
        // names in sync it will still work. $this->targetNames is in the desired
        // order due to the use of configuration dependencies in
        // \Drupal\Core\Config\StorageComparer::getAndSortConfigData().
        // Node type is a good example of a configuration entity that renames other
        // configuration when it is renamed.
        // @see \Drupal\node\Entity\NodeType::postSave()
        foreach ($this->target_names[$collection] as $name) {
            $data = $this->get_target_storage($collection)->read($name);
            if (isset($data['uuid']) && isset($create_uuids[$data['uuid']])) {
                // Remove the item from the create list.
                $this->remove_from_changelist($collection, 'create', $create_uuids[$data['uuid']]);
                // Remove the item from the delete list.
                $this->remove_from_changelist($collection, 'delete', $name);
                // Create the rename name.
                $renames[] = $this->create_rename_name($name, $create_uuids[$data['uuid']]);
            }
        }
        $this->add_change_list($collection, 'rename', $renames);
    }
    /**
     * Removes the entry from the given operation changelist for the given name.
     *
     * @param string $collection
     *   The storage collection to operate on.
     * @param string $op
     *   The changelist to act on. Either delete, create, rename or update.
     * @param string $name
     *   The name of the configuration to remove.
     */
    protected function remove_from_changelist($collection, $op, $name)
    {
        $key = array_search($name, $this->changelist[$collection][$op]);
        if ($key !== false) {
            unset($this->changelist[$collection][$op][$key]);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function move_rename_to_update($rename, $collection = Storage_Interface::DEFAULT_COLLECTION): void
    {
        $names = $this->extract_rename_names($rename);
        $this->remove_from_changelist($collection, 'rename', $rename);
        $this->add_change_list($collection, 'update', [$names['new_name']], $this->source_names[$collection]);
    }
    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->changelist = [Storage_Interface::DEFAULT_COLLECTION => $this->get_empty_changelist()];
        $this->source_names = $this->target_names = [];
        // Reset the static configuration data caches.
        $this->source_cache_storage->delete_all();
        $this->target_cache_storage->delete_all();
        return $this->create_changelist();
    }
    /**
     * {@inheritdoc}
     */
    public function has_changes(): bool
    {
        foreach ($this->get_all_collection_names() as $collection) {
            foreach (['delete', 'create', 'update', 'rename'] as $op) {
                if (!empty($this->changelist[$collection][$op])) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function validate_site_uuid(): bool
    {
        $source = $this->source_storage->read('system.site');
        $target = $this->target_storage->read('system.site');
        // It is possible that the storage does not contain system.site
        // configuration. In such cases the site UUID cannot be valid.
        return $source && $target && $source['uuid'] === $target['uuid'];
    }
    /**
     * Gets and sorts configuration data from the source and target storages.
     */
    protected function get_and_sort_config_data($collection)
    {
        $source_storage = $this->get_source_storage($collection);
        $target_storage = $this->get_target_storage($collection);
        $target_names = $target_storage->list_all();
        $source_names = $source_storage->list_all();
        // Prime the static caches by reading all the configuration in the source
        // and target storages.
        $target_data = $target_storage->read_multiple($target_names);
        $source_data = $source_storage->read_multiple($source_names);
        // If the collection only supports simple configuration do not use
        // configuration dependencies.
        if ($collection == Storage_Interface::DEFAULT_COLLECTION) {
            $dependency_manager = new Config_Dependency_Manager();
            $this->target_names[$collection] = $dependency_manager->set_data($target_data)->sort_all();
            $this->source_names[$collection] = $dependency_manager->set_data($source_data)->sort_all();
        } else {
            $this->target_names[$collection] = $target_names;
            $this->source_names[$collection] = $source_names;
        }
    }
    /**
     * Creates a rename name from the old and new names for the object.
     *
     * @param string $old_name
     *   The old configuration object name.
     * @param string $new_name
     *   The new configuration object name.
     *
     * @return string
     *   The configuration change name that encodes both the old and the new name.
     *
     * @see \Drupal\Core\Config\StorageComparerInterface::extractRenameNames()
     */
    protected function create_rename_name(string $old_name, string $new_name): string
    {
        return $old_name . '::' . $new_name;
    }
    /**
     * {@inheritdoc}
     */
    public function extract_rename_names($name): array
    {
        $names = explode('::', $name, 2);
        return ['old_name' => $names[0], 'new_name' => $names[1]];
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_all_collection_names($include_default = true): array
    {
        $collections = array_unique(array_merge($this->source_storage->get_all_collection_names(), $this->target_storage->get_all_collection_names()));
        if ($include_default) {
            array_unshift($collections, Storage_Interface::DEFAULT_COLLECTION);
        }
        return $collections;
    }
    /**
     * {@inheritdoc}
     */
    public function __sleep(): array
    {
        return array_diff($this->default_sleep(), ['targetStorages']);
    }
    /**
     * {@inheritdoc}
     */
    public function __wakeup(): void
    {
        $this->default_wakeup();
        $this->target_storages = [];
        $this->target_cache_storage->delete_all();
    }
}