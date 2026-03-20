<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Cache\Cache;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Defines the configuration object factory.
 *
 * The configuration object factory instantiates a Config object for each
 * configuration object name that is accessed and returns it to callers.
 *
 * @see \Drupal\Core\Config\Config
 *
 * Each configuration object gets a storage object injected, which
 * is used for reading and writing the configuration data.
 *
 * @see \Drupal\Core\Config\StorageInterface
 *
 * @ingroup config_api
 */
class Config_Factory implements Config_Factory_Interface, Event_Subscriber_Interface
{
    /**
     * Cached configuration objects.
     *
     * @var \Drupal\Core\Config\Config[]
     */
    protected $cache = [];
    /**
     * An array of config factory override objects ordered by priority.
     *
     * @var \Drupal\Core\Config\ConfigFactoryOverrideInterface[]
     */
    protected $config_factory_overrides = [];
    /**
     * Constructs the Config factory.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The configuration storage engine.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   An event dispatcher instance to use for configuration events.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
     *   The typed configuration manager.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Interface $storage, protected \Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface $event_dispatcher, protected \Drupal\Core\Config\Typed_Config_Manager_Interface $typed_config_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_editable($name)
    {
        return $this->do_get($name, false);
    }
    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->do_get($name);
    }
    /**
     * Returns a configuration object for a given name.
     *
     * @param string $name
     *   The name of the configuration object to construct.
     * @param bool $immutable
     *   (optional) Create an immutable configuration object. Defaults to TRUE.
     *
     * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
     *   A configuration object.
     */
    protected function do_get($name, $immutable = true)
    {
        if ($config = $this->do_load_multiple([$name], $immutable)) {
            return $config[$name];
        }
        // If the configuration object does not exist in the configuration
        // storage, create a new object.
        $config = $this->create_config_object($name, $immutable);
        if ($immutable) {
            // Get and apply any overrides.
            $overrides = $this->load_overrides([$name]);
            if (isset($overrides[$name])) {
                $config->set_module_override($overrides[$name]);
            }
            // Apply any settings.php overrides.
            if (isset($GLOBALS['config'][$name])) {
                $config->set_settings_override($GLOBALS['config'][$name]);
            }
        }
        foreach ($this->config_factory_overrides as $override) {
            $config->add_cacheable_dependency($override->get_cacheable_metadata($name));
        }
        return $config;
    }
    /**
     * {@inheritdoc}
     */
    public function load_multiple(array $names)
    {
        return $this->do_load_multiple($names);
    }
    /**
     * Returns a list of configuration objects for the given names.
     *
     * @param array $names
     *   List of names of configuration objects.
     * @param bool $immutable
     *   (optional) Create an immutable configuration objects. Defaults to TRUE.
     *
     * @return \Drupal\Core\Config\Config[]|\Drupal\Core\Config\ImmutableConfig[]
     *   List of successfully loaded configuration objects, keyed by name.
     */
    protected function do_load_multiple(array $names, $immutable = true): array
    {
        $list = [];
        foreach ($names as $key => $name) {
            $cache_key = $this->get_config_cache_key($name, $immutable);
            if (isset($this->cache[$cache_key])) {
                $list[$name] = $this->cache[$cache_key];
                unset($names[$key]);
            }
        }
        // Pre-load remaining configuration files.
        if (!empty($names)) {
            // Initialize override information.
            $module_overrides = [];
            $storage_data = $this->storage->read_multiple($names);
            if ($immutable && !empty($storage_data)) {
                // Only get module overrides if we have configuration to override.
                $module_overrides = $this->load_overrides($names);
            }
            foreach ($storage_data as $name => $data) {
                $cache_key = $this->get_config_cache_key($name, $immutable);
                $this->cache[$cache_key] = $this->create_config_object($name, $immutable);
                $this->cache[$cache_key]->init_with_data($data);
                if ($immutable) {
                    if (isset($module_overrides[$name])) {
                        $this->cache[$cache_key]->set_module_override($module_overrides[$name]);
                    }
                    if (isset($GLOBALS['config'][$name])) {
                        $this->cache[$cache_key]->set_settings_override($GLOBALS['config'][$name]);
                    }
                }
                $this->propagate_config_override_cacheability($cache_key, $name);
                $list[$name] = $this->cache[$cache_key];
            }
        }
        return $list;
    }
    /**
     * Get arbitrary overrides for the named configuration objects from modules.
     *
     * @param array $names
     *   The names of the configuration objects to get overrides for.
     *
     * @return array
     *   An array of overrides keyed by the configuration object name.
     */
    protected function load_overrides(array $names): array
    {
        $overrides = [];
        foreach ($this->config_factory_overrides as $override) {
            // Existing overrides take precedence since these will have been added
            // by events with a higher priority.
            $overrides = Nested_Array::merge_deep_array([$override->load_overrides($names), $overrides], true);
        }
        return $overrides;
    }
    /**
     * Propagates cacheability of config overrides to cached config objects.
     *
     * @param string $cache_key
     *   The key of the cached config object to update.
     * @param string $name
     *   The name of the configuration object to construct.
     */
    protected function propagate_config_override_cacheability($cache_key, $name)
    {
        foreach ($this->config_factory_overrides as $override) {
            $this->cache[$cache_key]->add_cacheable_dependency($override->get_cacheable_metadata($name));
        }
    }
    /**
     * {@inheritdoc}
     */
    public function reset($name = null): static
    {
        if ($name) {
            // Clear all cached configuration for this name.
            foreach ($this->get_config_cache_keys($name) as $cache_key) {
                unset($this->cache[$cache_key]);
            }
        } else {
            $this->cache = [];
        }
        // Clear the static list cache if supported by the storage.
        if ($this->storage instanceof Storage_Cache_Interface) {
            $this->storage->reset_list_cache();
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function rename($old_name, $new_name): static
    {
        Cache::invalidate_tags($this->get($old_name)->get_cache_tags());
        $this->storage->rename($old_name, $new_name);
        // Clear out the static cache of any references to the old name.
        foreach ($this->get_config_cache_keys($old_name) as $old_cache_key) {
            unset($this->cache[$old_cache_key]);
        }
        // Prime the cache and load the configuration with the correct overrides.
        $config = $this->get($new_name);
        $event_name = $this->storage->get_collection_name() === Storage_Interface::DEFAULT_COLLECTION ? Config_Events::RENAME : Config_Collection_Events::RENAME_IN_COLLECTION;
        $this->event_dispatcher->dispatch(new Config_Rename_Event($config, $old_name), $event_name);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_keys()
    {
        // Because get() adds overrides both from $GLOBALS and from
        // $this->configFactoryOverrides, add cache keys for each.
        $keys[] = 'global_overrides';
        foreach ($this->config_factory_overrides as $override) {
            $keys[] = $override->get_cache_suffix();
        }
        return $keys;
    }
    /**
     * Gets the static cache key for a given config name.
     *
     * @param string $name
     *   The name of the configuration object.
     * @param bool $immutable
     *   Whether or not the object is mutable.
     *
     * @return string
     *   The cache key.
     */
    protected function get_config_cache_key(string $name, $immutable): string
    {
        $suffix = '';
        if ($immutable) {
            $suffix = ':' . implode(':', $this->get_cache_keys());
        }
        return $name . $suffix;
    }
    /**
     * Gets all the cache keys that match the provided config name.
     *
     * @param string $name
     *   The name of the configuration object.
     *
     * @return array
     *   An array of cache keys that match the provided config name.
     */
    protected function get_config_cache_keys($name): array
    {
        return array_filter(
            array_keys($this->cache),
            // Return TRUE if the key is the name or starts with the configuration
            // name plus the delimiter.
            fn(int|string $key) => $key === $name || str_starts_with((string) $key, $name . ':')
        );
    }
    /**
     * {@inheritdoc}
     */
    public function clear_static_cache(): static
    {
        $this->cache = [];
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function list_all($prefix = '')
    {
        return $this->storage->list_all($prefix);
    }
    /**
     * Updates stale static cache entries when configuration is saved.
     *
     * @param ConfigCrudEvent $event
     *   The configuration event.
     */
    public function on_config_save(Config_Crud_Event $event): void
    {
        $saved_config = $event->get_config();
        // We are only concerned with config objects that belong to the collection
        // that matches the storage we depend on. Skip if the event was fired for a
        // config object belonging to a different collection.
        if ($saved_config->get_storage()->get_collection_name() !== $this->storage->get_collection_name()) {
            return;
        }
        // Ensure that the static cache contains up to date configuration objects by
        // replacing the data on any entries for the configuration object apart
        // from the one that references the actual config object being saved.
        foreach ($this->get_config_cache_keys($saved_config->get_name()) as $cache_key) {
            $cached_config = $this->cache[$cache_key];
            if ($cached_config !== $saved_config) {
                // We can not just update the data since other things about the object
                // might have changed. For example, whether or not it is new.
                $this->cache[$cache_key]->init_with_data($saved_config->get_raw_data());
            }
        }
    }
    /**
     * Removes stale static cache entries when configuration is deleted.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The configuration event.
     */
    public function on_config_delete(Config_Crud_Event $event): void
    {
        $deleted_config = $event->get_config();
        // We are only concerned with config objects that belong to the collection
        // that matches the storage we depend on. Skip if the event was fired for a
        // config object belonging to a different collection.
        if ($deleted_config->get_storage()->get_collection_name() !== $this->storage->get_collection_name()) {
            return;
        }
        // Ensure that the static cache does not contain deleted configuration.
        foreach ($this->get_config_cache_keys($deleted_config->get_name()) as $cache_key) {
            unset($this->cache[$cache_key]);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Config_Events::SAVE][] = ['onConfigSave', 255];
        $events[Config_Events::DELETE][] = ['onConfigDelete', 255];
        return $events;
    }
    /**
     * {@inheritdoc}
     */
    public function add_override(Config_Factory_Override_Interface $config_factory_override): void
    {
        $this->config_factory_overrides[] = $config_factory_override;
    }
    /**
     * Creates a configuration object.
     *
     * @param string $name
     *   Configuration object name.
     * @param bool $immutable
     *   Determines whether a mutable or immutable config object is returned.
     *
     * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
     *   The configuration object.
     */
    protected function create_config_object($name, $immutable): \Drupal\Core\Config\Immutable_Config|\Drupal\Core\Config\Config
    {
        if ($immutable) {
            return new Immutable_Config($name, $this->storage, $this->event_dispatcher, $this->typed_config_manager);
        }
        return new Config($name, $this->storage, $this->event_dispatcher, $this->typed_config_manager);
    }
}