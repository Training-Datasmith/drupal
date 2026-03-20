<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Component\Uuid\Uuid_Interface;
use Drupal\Core\Cache\Cacheable_Metadata;
use Drupal\Core\Cache\Memory_Cache\Memory_Cache_Interface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Config_Importer_Exception;
use Drupal\Core\Config\Entity\Exception\Config_Entity_Id_Length_Exception;
use Drupal\Core\Entity\Entity_Interface;
use Drupal\Core\Entity\Entity_Malformed_Exception;
use Drupal\Core\Entity\Entity_Storage_Base;
use Drupal\Core\Entity\Entity_Type_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Defines the storage class for configuration entities.
 *
 * Configuration object names of configuration entities are comprised of two
 * parts, separated by a dot:
 * - config_prefix: A string denoting the owner (module/extension) of the
 *   configuration object, followed by arbitrary other namespace identifiers
 *   that are declared by the owning extension; e.g., 'node.type'. The
 *   config_prefix does NOT contain a trailing dot. It is defined by the entity
 *   type's annotation.
 * - ID: A string denoting the entity ID within the entity type namespace; e.g.,
 *   'article'. Entity IDs may contain dots/periods. The entire remaining string
 *   after the config_prefix in a config name forms the entity ID. Additional or
 *   custom suffixes are not possible.
 *
 * @ingroup entity_api
 */
class Config_Entity_Storage extends Entity_Storage_Base implements Config_Entity_Storage_Interface, Importable_Entity_Storage_Interface
{
    /**
     * Length limit of the configuration entity ID.
     *
     * Most file systems limit a file name's length to 255 characters, so
     * ConfigBase::MAX_NAME_LENGTH restricts the full configuration object name
     * to 250 characters (leaving 5 for the file extension). The config prefix
     * is limited by ConfigEntityType::PREFIX_LENGTH to 83 characters, so this
     * leaves 166 remaining characters for the configuration entity ID, with 1
     * additional character needed for the joining dot.
     *
     * @see \Drupal\Core\Config\ConfigBase::MAX_NAME_LENGTH
     * @see \Drupal\Core\Config\Entity\ConfigEntityType::PREFIX_LENGTH
     */
    public const MAX_ID_LENGTH = 166;
    /**
     * {@inheritdoc}
     */
    protected $uuid_key = 'uuid';
    /**
     * The config storage service.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $config_storage;
    /**
     * Determines if the underlying configuration is retrieved override free.
     *
     * @var bool
     */
    protected $override_free = false;
    /**
     * Constructs a ConfigEntityStorage object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory service.
     * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
     *   The UUID service.
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
     *   The memory cache backend.
     */
    public function __construct(Entity_Type_Interface $entity_type, protected \Drupal\Core\Config\Config_Factory_Interface $config_factory, Uuid_Interface $uuid_service, protected \Drupal\Core\Language\Language_Manager_Interface $language_manager, Memory_Cache_Interface $memory_cache)
    {
        parent::__construct($entity_type, $memory_cache);
        $this->uuid_service = $uuid_service;
    }
    /**
     * {@inheritdoc}
     */
    public static function create_instance(Container_Interface $container, Entity_Type_Interface $entity_type): static
    {
        return new static($entity_type, $container->get('config.factory'), $container->get('uuid'), $container->get('language_manager'), $container->get('entity.memory_cache'));
    }
    /**
     * Returns the prefix used to create the configuration name.
     *
     * The prefix consists of the config prefix from the entity type plus a dot
     * for separating from the ID.
     *
     * @return string
     *   The full configuration prefix, for example 'views.view.'.
     */
    protected function get_prefix(): string
    {
        return $this->entity_type->get_config_prefix() . '.';
    }
    /**
     * {@inheritdoc}
     */
    public static function get_id_from_config_name($config_name, $config_prefix): string
    {
        return substr($config_name, strlen($config_prefix . '.'));
    }
    /**
     * {@inheritdoc}
     */
    protected function do_load_multiple(?array $ids = null)
    {
        $prefix = $this->get_prefix();
        // Get the names of the configuration entities we are going to load.
        if ($ids === null) {
            $names = $this->config_factory->list_all($prefix);
        } else {
            $names = [];
            foreach ($ids as $id) {
                // Add the prefix to the ID to serve as the configuration object name.
                $names[] = $prefix . $id;
            }
        }
        // Load all of the configuration entities.
        /** @var \Drupal\Core\Config\Config[] $configs */
        $configs = [];
        $records = [];
        foreach ($this->config_factory->load_multiple($names) as $config) {
            $id = $config->get($this->id_key);
            $records[$id] = $this->override_free ? $config->get_original(null, false) : $config->get();
            $configs[$id] = $config;
        }
        $entities = $this->map_from_storage_records($records);
        // Config entities wrap config objects, and therefore they need to inherit
        // the cacheability metadata of config objects (to ensure e.g. additional
        // cacheability metadata added by config overrides is not lost).
        foreach ($entities as $id => $entity) {
            // But rather than simply inheriting all cacheability metadata of config
            // objects, we need to make sure the self-referring cache tag that is
            // present on Config objects is not added to the Config entity. It must be
            // removed for 3 reasons:
            // 1. When renaming/duplicating a Config entity, the cache tag of the
            //    original config object would remain present, which would be wrong.
            // 2. Some Config entities choose to not use the cache tag that the under-
            //    lying Config object provides by default (For performance and
            //    cacheability reasons it may not make sense to have a unique cache
            //    tag for every Config entity. The DateFormat Config entity specifies
            //    the 'rendered' cache tag for example, because A) date formats are
            //    changed extremely rarely, so invalidating all render cache items is
            //    fine, B) it means fewer cache tags per page.).
            // 3. Fewer cache tags is better for performance.
            $self_referring_cache_tag = ['config:' . $configs[$id]->get_name()];
            $config_cacheability = Cacheable_Metadata::create_from_object($configs[$id]);
            $config_cacheability->set_cache_tags(array_diff($config_cacheability->get_cache_tags(), $self_referring_cache_tag));
            $entity->add_cacheable_dependency($config_cacheability);
        }
        return $entities;
    }
    /**
     * {@inheritdoc}
     */
    protected function do_create(array $values)
    {
        // Set default language to current language if not provided.
        $values += [$this->langcode_key => $this->language_manager->get_current_language()->get_id()];
        $entity_class = $this->get_entity_class();
        return new $entity_class($values, $this->entity_type_id);
    }
    /**
     * {@inheritdoc}
     */
    protected function do_delete($entities)
    {
        foreach ($entities as $entity) {
            $this->config_factory->get_editable($this->get_prefix() . $entity->id())->delete();
        }
    }
    /**
     * Implements Drupal\Core\Entity\EntityStorageInterface::save().
     *
     * @throws \Drupal\Core\Entity\EntityMalformedException
     *   When attempting to save a configuration entity that has no ID.
     */
    public function save(Entity_Interface $entity)
    {
        // Configuration entity IDs are strings, and '0' is a valid ID.
        $id = $entity->id();
        if ($id === null || $id === '') {
            throw new Entity_Malformed_Exception('The entity does not have an ID.');
        }
        // Check the configuration entity ID length.
        // @see \Drupal\Core\Config\Entity\ConfigEntityStorage::MAX_ID_LENGTH
        // @todo Consider moving this to a protected method on the parent class, and
        //   abstracting it for all entity types.
        if (strlen((string) $id) > static::MAX_ID_LENGTH) {
            throw new Config_Entity_Id_Length_Exception("Configuration entity ID {$id} exceeds maximum allowed length of " . static::MAX_ID_LENGTH . ' characters.');
        }
        return parent::save($entity);
    }
    /**
     * {@inheritdoc}
     */
    protected function do_save($id, Entity_Interface $entity): int
    {
        $is_new = $entity->is_new();
        $prefix = $this->get_prefix();
        $config_name = $prefix . $entity->id();
        if ($id !== $entity->id()) {
            // Renaming a config object needs to cater for:
            // - Storage needs to access the original object.
            // - The object needs to be renamed/copied in ConfigFactory and reloaded.
            // - All instances of the object need to be renamed.
            $this->config_factory->rename($prefix . $id, $config_name);
        }
        $config = $this->config_factory->get_editable($config_name);
        // Retrieve the desired properties and set them in config.
        $config->set_data($this->map_to_storage_record($entity));
        $config->save($entity->has_trusted_data());
        // Update the entity with the values stored in configuration. It is possible
        // that configuration schema has casted some of the values.
        if (!$entity->has_trusted_data()) {
            $data = $this->map_from_storage_records([$config->get()]);
            $updated_entity = current($data);
            foreach (array_keys($config->get()) as $property) {
                $value = $updated_entity->get($property);
                $entity->set($property, $value);
            }
        }
        return $is_new ? SAVED_NEW : SAVED_UPDATED;
    }
    /**
     * Maps from an entity object to the storage record.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity object.
     *
     * @return array
     *   The record to store.
     */
    protected function map_to_storage_record(Entity_Interface $entity)
    {
        return $entity->to_array();
    }
    /**
     * {@inheritdoc}
     */
    protected function has($id, Entity_Interface $entity): bool
    {
        $prefix = $this->get_prefix();
        $config = $this->config_factory->get($prefix . $id);
        return !$config->is_new();
    }
    /**
     * {@inheritdoc}
     */
    public function has_data(): bool
    {
        return (bool) $this->config_factory->list_all($this->get_prefix());
    }
    /**
     * {@inheritdoc}
     */
    protected function build_cache_id($id): string
    {
        return parent::build_cache_id($id) . ':' . ($this->override_free ? '' : implode(':', $this->config_factory->get_cache_keys()));
    }
    /**
     * {@inheritdoc}
     */
    public function reset_cache(?array $ids = null): void
    {
        if ($this->entity_type->is_statically_cacheable()) {
            // Always invalidate through the cache tag, since config entities may
            // be cached under different cache keys depending on the override flag.
            $this->memory_cache->invalidate_tags([$this->memory_cache_tag]);
        }
    }
    /**
     * Invokes a hook on behalf of the entity.
     *
     * @param string $hook
     *   One of 'presave', 'insert', 'update', 'predelete', or 'delete'.
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity object.
     */
    protected function invoke_hook($hook, Entity_Interface $entity)
    {
        // Invoke the hook.
        $this->module_handler->invoke_all($this->entity_type_id . '_' . $hook, [$entity]);
        // Invoke the respective entity-level hook.
        $this->module_handler->invoke_all('entity_' . $hook, [$entity, $this->entity_type_id]);
    }
    /**
     * {@inheritdoc}
     */
    protected function get_query_service_name(): string
    {
        return 'entity.query.config';
    }
    /**
     * {@inheritdoc}
     */
    public function import_create($name, Config $new_config, Config $old_config): bool
    {
        $entity = $this->_do_create_from_storage_record($new_config->get(), true);
        $entity->save();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function import_update($name, Config $new_config, Config $old_config): bool
    {
        $id = static::get_id_from_config_name($name, $this->entity_type->get_config_prefix());
        $entity = $this->load($id);
        if (!$entity) {
            throw new Config_Importer_Exception("Attempt to update non-existing entity '{$id}'.");
        }
        $entity->set_syncing(true);
        $entity = $this->update_from_storage_record($entity, $new_config->get());
        $entity->save();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function import_delete($name, Config $new_config, Config $old_config): bool
    {
        $id = static::get_id_from_config_name($name, $this->entity_type->get_config_prefix());
        $entity = $this->load($id);
        $entity->set_syncing(true);
        $entity->delete();
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function import_rename($old_name, Config $new_config, Config $old_config)
    {
        return $this->import_update($old_name, $new_config, $old_config);
    }
    /**
     * {@inheritdoc}
     */
    public function create_from_storage_record(array $values)
    {
        return $this->_do_create_from_storage_record($values);
    }
    /**
     * Helps create a configuration entity from storage values.
     *
     * Allows the configuration entity storage to massage storage values before
     * creating an entity.
     *
     * @param array $values
     *   The array of values from the configuration storage.
     * @param bool $is_syncing
     *   Is the configuration entity being created as part of a config sync.
     *
     * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
     *   The configuration entity.
     *
     * @see \Drupal\Core\Config\Entity\ConfigEntityStorageInterface::createFromStorageRecord()
     * @see \Drupal\Core\Config\Entity\ImportableEntityStorageInterface::importCreate()
     */
    protected function _do_create_from_storage_record(array $values, $is_syncing = false)
    {
        // Assign a new UUID if there is none yet.
        if ($this->uuid_key && $this->uuid_service && !isset($values[$this->uuid_key])) {
            $values[$this->uuid_key] = $this->uuid_service->generate();
        }
        $data = $this->map_from_storage_records([$values]);
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
        $entity = current($data);
        $entity->set_original(clone $entity);
        $entity->set_syncing($is_syncing);
        $entity->enforce_is_new();
        $entity->post_create($this);
        // Modules might need to add or change the data initially held by the new
        // entity object, for instance to fill-in default values.
        $this->invoke_hook('create', $entity);
        return $entity;
    }
    /**
     * {@inheritdoc}
     */
    public function update_from_storage_record(Config_Entity_Interface $entity, array $values): Config_Entity_Interface
    {
        $entity->set_original(clone $entity);
        $data = $this->map_from_storage_records([$values]);
        $updated_entity = current($data);
        /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
        $entity_type = $this->get_entity_type();
        $id_key = $entity_type->get_key('id');
        $properties = $entity_type->get_properties_to_export($updated_entity->get($id_key));
        if (empty($properties)) {
            // Fallback to using the provided values. If the properties cannot be
            // determined for the config entity type annotation or configuration
            // schema.
            $properties = array_keys($values);
        }
        foreach ($properties as $property) {
            if ($property === $this->uuid_key) {
                // During an update the UUID field should not be copied. Under regular
                // circumstances the values will be equal. If configuration is written
                // twice during configuration install the updated entity will not have a
                // UUID.
                // @see \Drupal\Core\Config\ConfigInstaller::createConfiguration()
                continue;
            }
            $entity->set($property, $updated_entity->get($property));
        }
        return $entity;
    }
    /**
     * {@inheritdoc}
     */
    public function load_override_free($id)
    {
        $entities = $this->load_multiple_override_free([$id]);
        return $entities[$id] ?? null;
    }
    /**
     * {@inheritdoc}
     */
    public function load_multiple_override_free(?array $ids = null)
    {
        $this->override_free = true;
        $entities = $this->load_multiple($ids);
        $this->override_free = false;
        return $entities;
    }
}