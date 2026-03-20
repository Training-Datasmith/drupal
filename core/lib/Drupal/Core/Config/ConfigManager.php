<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Diff\Diff;
use Drupal\Core\Config\Entity\Config_Dependency_Manager;
use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Config\Entity\Config_Entity_Type_Interface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translation_Interface;
/**
 * The ConfigManager provides helper functions for the configuration system.
 */
class Config_Manager implements Config_Manager_Interface
{
    use String_Translation_Trait;
    use Storage_Copy_Trait;
    /**
     * The configuration collection info.
     *
     * @var \Drupal\Core\Config\ConfigCollectionInfo
     */
    protected $config_collection_info;
    /**
     * The configuration storages keyed by collection name.
     *
     * @var \Drupal\Core\Config\StorageInterface[]
     */
    protected $storages;
    /**
     * Creates ConfigManager objects.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The configuration factory.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
     *   The typed config manager.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The string translation service.
     * @param \Drupal\Core\Config\StorageInterface $activeStorage
     *   The active configuration storage.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   The event dispatcher.
     * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
     *   The entity repository.
     * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
     *   The extension path resolver.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Type_Manager_Interface $entity_type_manager, protected \Drupal\Core\Config\Config_Factory_Interface $config_factory, protected \Drupal\Core\Config\Typed_Config_Manager_Interface $typed_config_manager, Translation_Interface $string_translation, protected \Drupal\Core\Config\Storage_Interface $active_storage, protected \Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface $event_dispatcher, protected \Drupal\Core\Entity\Entity_Repository_Interface $entity_repository, protected \Drupal\Core\Extension\Extension_Path_Resolver $extension_path_resolver)
    {
        $this->string_translation = $string_translation;
    }
    /**
     * {@inheritdoc}
     */
    public function get_entity_type_id_by_name($name)
    {
        foreach ($this->entity_type_manager->get_definitions() as $entity_type_id => $entity_type) {
            if ($entity_type instanceof Config_Entity_Type_Interface && ($config_prefix = $entity_type->get_config_prefix()) && str_starts_with($name, $config_prefix . '.')) {
                return $entity_type_id;
            }
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function load_config_entity_by_name($name)
    {
        $entity_type_id = $this->get_entity_type_id_by_name($name);
        if ($entity_type_id) {
            $entity_type = $this->entity_type_manager->get_definition($entity_type_id);
            $id = substr($name, strlen((string) $entity_type->get_config_prefix()) + 1);
            return $this->entity_type_manager->get_storage($entity_type_id)->load($id);
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_entity_type_manager()
    {
        return $this->entity_type_manager;
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_factory()
    {
        return $this->config_factory;
    }
    /**
     * {@inheritdoc}
     */
    public function diff(Storage_Interface $source_storage, Storage_Interface $target_storage, $source_name, $target_name = null, $collection = Storage_Interface::DEFAULT_COLLECTION): \Drupal\Component\Diff\Diff
    {
        if ($collection != Storage_Interface::DEFAULT_COLLECTION) {
            $source_storage = $source_storage->create_collection($collection);
            $target_storage = $target_storage->create_collection($collection);
        }
        if (!isset($target_name)) {
            $target_name = $source_name;
        }
        // The output should show configuration object differences formatted as
        // YAML. But the configuration is not necessarily stored in files.
        // Therefore, they need to be read and parsed, and lastly, dumped into YAML
        // strings.
        $source_data = explode("\n", Yaml::encode($source_storage->read($source_name)));
        $target_data = explode("\n", Yaml::encode($target_storage->read($target_name)));
        // Check for new or removed files.
        if ($source_data === ['false']) {
            // Added file.
            // Cast the result of t() to a string, as the diff engine doesn't know
            // about objects.
            $source_data = [(string) $this->t('File added')];
        }
        if ($target_data === ['false']) {
            // Deleted file.
            // Cast the result of t() to a string, as the diff engine doesn't know
            // about objects.
            $target_data = [(string) $this->t('File removed')];
        }
        return new Diff($source_data, $target_data);
    }
    /**
     * {@inheritdoc}
     */
    public function create_snapshot(Storage_Interface $source_storage, Storage_Interface $snapshot_storage): void
    {
        self::replace_storage_contents($source_storage, $snapshot_storage);
    }
    /**
     * {@inheritdoc}
     */
    public function uninstall($type, $name): void
    {
        $entities = $this->get_config_entities_to_change_on_dependency_removal($type, [$name], false);
        // Fix all dependent configuration entities.
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
        foreach ($entities['update'] as $entity) {
            $entity->save();
        }
        // Remove all dependent configuration entities.
        foreach ($entities['delete'] as $entity) {
            $entity->set_uninstalling(true);
            $entity->delete();
        }
        $config_names = $this->config_factory->list_all($name . '.');
        foreach ($config_names as $config_name) {
            $this->config_factory->get_editable($config_name)->delete();
        }
        // Remove any matching configuration from collections.
        foreach ($this->active_storage->get_all_collection_names() as $collection) {
            $collection_storage = $this->active_storage->create_collection($collection);
            $overrider = $this->get_config_collection_info()->get_override_service($collection);
            foreach ($collection_storage->list_all($name . '.') as $config_name) {
                if ($overrider) {
                    $config = $overrider->create_config_object($config_name, $collection);
                } else {
                    $config = new Config($config_name, $collection_storage, $this->event_dispatcher, $this->typed_config_manager);
                }
                $config->init_with_data($collection_storage->read($config_name));
                $config->delete();
            }
        }
        $schema_dir = $this->extension_path_resolver->get_path($type, $name) . '/' . Install_Storage::CONFIG_SCHEMA_DIRECTORY;
        if (is_dir($schema_dir)) {
            // Refresh the schema cache if uninstalling an extension that provides
            // configuration schema.
            $this->typed_config_manager->clear_cached_definitions();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_dependency_manager(): \Drupal\Core\Config\Entity\Config_Dependency_Manager
    {
        $dependency_manager = new Config_Dependency_Manager();
        // Read all configuration using the factory. This ensures that multiple
        // deletes during the same request benefit from the static cache. Using the
        // factory also ensures configuration entity dependency discovery has no
        // dependencies on the config entity classes. Assume data with UUID is a
        // config entity. Only configuration entities can be depended on so we can
        // ignore everything else.
        $data = array_map(function (\Drupal\Core\Config\Immutable_Config $config) {
            $data = $config->get();
            if (isset($data['uuid'])) {
                return $data;
            }
            return false;
        }, $this->config_factory->load_multiple($this->active_storage->list_all()));
        $dependency_manager->set_data(array_filter($data));
        return $dependency_manager;
    }
    /**
     * {@inheritdoc}
     */
    public function find_config_entity_dependencies($type, array $names, ?Config_Dependency_Manager $dependency_manager = null): array
    {
        if (!$dependency_manager) {
            $dependency_manager = $this->get_config_dependency_manager();
        }
        $dependencies = [];
        foreach ($names as $name) {
            $dependencies[] = $dependency_manager->get_dependent_entities($type, $name);
        }
        return array_merge(...$dependencies);
    }
    /**
     * {@inheritdoc}
     */
    public function find_config_entity_dependencies_as_entities($type, array $names, ?Config_Dependency_Manager $dependency_manager = null): array
    {
        $dependencies = $this->find_config_entity_dependencies($type, $names, $dependency_manager);
        $entities = [];
        $definitions = $this->entity_type_manager->get_definitions();
        foreach ($dependencies as $config_name => $dependency) {
            // Group by entity type to efficient load entities using
            // \Drupal\Core\Entity\EntityStorageInterface::loadMultiple().
            $entity_type_id = $this->get_entity_type_id_by_name($config_name);
            // It is possible that a non-configuration entity will be returned if a
            // simple configuration object has a UUID key. This would occur if the
            // dependents of the system module are calculated since system.site has
            // a UUID key.
            if ($entity_type_id) {
                $id = substr((string) $config_name, strlen((string) $definitions[$entity_type_id]->get_config_prefix()) + 1);
                $entities[$entity_type_id][$config_name] = $id;
            }
        }
        // Align the order of entities returned to the dependency order by first
        // populating the keys in the same order.
        $entities_to_return = array_fill_keys(array_keys($dependencies), null);
        foreach ($entities as $entity_type_id => $entities_to_load) {
            $storage = $this->entity_type_manager->get_storage($entity_type_id);
            $loaded_entities = $storage->load_multiple($entities_to_load);
            foreach ($loaded_entities as $loaded_entity) {
                $entities_to_return[$loaded_entity->get_config_dependency_name()] = $loaded_entity;
            }
        }
        // Return entities list with NULL entries removed.
        return array_filter($entities_to_return);
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_entities_to_change_on_dependency_removal($type, array $names, $dry_run = true): array
    {
        $dependency_manager = $this->get_config_dependency_manager();
        // Store the list of dependents in three separate variables. This allows us
        // to determine how the dependency graph changes as entities are fixed by
        // calling the onDependencyRemoval() method.
        // The list of original dependents on $names. This list never changes.
        $original_dependents = $this->find_config_entity_dependencies_as_entities($type, $names, $dependency_manager);
        // The current list of dependents on $names. This list is recalculated when
        // calling an entity's onDependencyRemoval() method results in the entity
        // changing. This list is passed to each entity's onDependencyRemoval()
        // method as the list of affected entities.
        $current_dependents = $original_dependents;
        // The list of dependents to process. This list changes as entities are
        // processed and are either fixed or deleted.
        $dependents_to_process = $original_dependents;
        // Initialize other variables.
        $affected_uuids = [];
        $return = ['update' => [], 'delete' => [], 'unchanged' => []];
        // Try to fix the dependents and find out what will happen to the dependency
        // graph. Entities are processed in the order of most dependent first. For
        // example, this ensures that Menu UI third party dependencies on node types
        // are fixed before processing the node type's other dependents.
        while ($dependent = array_pop($dependents_to_process)) {
            /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $dependent */
            if ($dry_run) {
                // Clone the entity so any changes do not change any static caches.
                $dependent = clone $dependent;
            }
            $fixed = false;
            if ($this->call_on_dependency_removal($dependent, $current_dependents, $type, $names)) {
                // Recalculate dependencies and update the dependency graph data.
                $dependent->calculate_dependencies();
                $dependency_manager->update_data($dependent->get_config_dependency_name(), $dependent->get_dependencies());
                // Based on the updated data rebuild the list of current dependents.
                // This will remove entities that are no longer dependent after the
                // recalculation.
                $current_dependents = $this->find_config_entity_dependencies_as_entities($type, $names, $dependency_manager);
                // Rebuild the list of entities that we need to process using the new
                // list of current dependents and removing any entities that we've
                // already processed.
                $dependents_to_process = array_filter($current_dependents, fn(\Drupal\Core\Config\Entity\Config_Entity_Interface $current_dependent) => !in_array($current_dependent->uuid(), $affected_uuids));
                // Ensure that the dependent has actually been fixed. It is possible
                // that other dependencies cause it to still be in the list.
                $fixed = true;
                foreach ($dependents_to_process as $key => $entity) {
                    if ($entity->uuid() == $dependent->uuid()) {
                        $fixed = false;
                        unset($dependents_to_process[$key]);
                        break;
                    }
                }
                if ($fixed) {
                    $affected_uuids[] = $dependent->uuid();
                    $return['update'][] = $dependent;
                }
            }
            // If the entity cannot be fixed then it has to be deleted.
            if (!$fixed) {
                $affected_uuids[] = $dependent->uuid();
                // Deletes should occur in the order of the least dependent first. For
                // example, this ensures that fields are removed before field storages.
                array_unshift($return['delete'], $dependent);
            }
        }
        // Use the list of affected UUIDs to filter the original list to work out
        // which configuration entities are unchanged.
        $return['unchanged'] = array_filter($original_dependents, fn(\Drupal\Core\Config\Entity\Config_Entity_Interface $dependent) => !in_array($dependent->uuid(), $affected_uuids));
        return $return;
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_collection_info()
    {
        if (!isset($this->config_collection_info)) {
            $this->config_collection_info = new Config_Collection_Info();
            $this->event_dispatcher->dispatch($this->config_collection_info, Config_Collection_Events::COLLECTION_INFO);
        }
        return $this->config_collection_info;
    }
    /**
     * Calls an entity's onDependencyRemoval() method.
     *
     * A helper method to call onDependencyRemoval() with the correct list of
     * affected entities. This list should only contain dependencies on the
     * entity. Configuration and content entity dependencies will be converted
     * into entity objects.
     *
     * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
     *   The entity to call onDependencyRemoval() on.
     * @param \Drupal\Core\Config\Entity\ConfigEntityInterface[] $dependent_entities
     *   The list of dependent configuration entities.
     * @param string $type
     *   The type of dependency being checked. Either 'module', 'theme', 'config'
     *   or 'content'.
     * @param array $names
     *   The specific names to check. If $type equals 'module' or 'theme' then it
     *   should be a list of module names or theme names. In the case of 'config'
     *   or 'content' it should be a list of configuration dependency names.
     *
     * @return bool
     *   TRUE if the entity has changed as a result of calling the
     *   onDependencyRemoval() method, FALSE if not.
     */
    protected function call_on_dependency_removal(Config_Entity_Interface $entity, array $dependent_entities, $type, array $names)
    {
        $entity_dependencies = $entity->get_dependencies();
        if (empty($entity_dependencies)) {
            // No dependent entities nothing to do.
            return false;
        }
        $affected_dependencies = ['config' => [], 'content' => [], 'module' => [], 'theme' => []];
        // Work out if any of the entity's dependencies are going to be affected.
        if (isset($entity_dependencies[$type])) {
            // Work out which dependencies the entity has in common with the provided
            // $type and $names.
            $affected_dependencies[$type] = array_intersect($entity_dependencies[$type], $names);
            // If the dependencies are entities we need to convert them into objects.
            if ($type == 'config' || $type == 'content') {
                $affected_dependencies[$type] = array_map(function ($name) use ($type) {
                    if ($type == 'config') {
                        return $this->load_config_entity_by_name($name);
                    }
                    // Ignore the bundle.
                    [$entity_type_id, , $uuid] = explode(':', $name);
                    return $this->entity_repository->load_entity_by_config_target($entity_type_id, $uuid);
                }, $affected_dependencies[$type]);
            }
        }
        // Merge any other configuration entities into the list of affected
        // dependencies if necessary.
        if (isset($entity_dependencies['config'])) {
            foreach ($dependent_entities as $dependent_entity) {
                if (in_array($dependent_entity->get_config_dependency_name(), $entity_dependencies['config'])) {
                    $affected_dependencies['config'][] = $dependent_entity;
                }
            }
        }
        // Key the entity arrays by config dependency name to make searching easy.
        foreach (['config', 'content'] as $dependency_type) {
            $affected_dependencies[$dependency_type] = array_combine(array_map(fn($entity) => $entity->get_config_dependency_name(), $affected_dependencies[$dependency_type]), $affected_dependencies[$dependency_type]);
        }
        // Inform the entity.
        return $entity->on_dependency_removal($affected_dependencies);
    }
    /**
     * {@inheritdoc}
     * @return array{entity_type: string, bundle: string, uuid: string}[]
     */
    public function find_missing_content_dependencies(): array
    {
        $content_dependencies = [];
        $missing_dependencies = [];
        foreach ($this->active_storage->read_multiple($this->active_storage->list_all()) as $config_data) {
            if (isset($config_data['dependencies']['content'])) {
                $content_dependencies[] = $config_data['dependencies']['content'];
            }
            if (isset($config_data['dependencies']['enforced']['content'])) {
                $content_dependencies[] = $config_data['dependencies']['enforced']['content'];
            }
        }
        $unique_content_dependencies = array_unique(array_merge(...$content_dependencies));
        foreach ($unique_content_dependencies as $content_dependency) {
            // Format of the dependency is entity_type:bundle:uuid.
            [$entity_type, $bundle, $uuid] = explode(':', (string) $content_dependency, 3);
            if (!$this->entity_repository->load_entity_by_uuid($entity_type, $uuid)) {
                $missing_dependencies[$uuid] = ['entity_type' => $entity_type, 'bundle' => $bundle, 'uuid' => $uuid];
            }
        }
        return $missing_dependencies;
    }
}