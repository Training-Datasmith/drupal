<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Cache\Cache;
/**
 * Defines the default configuration object.
 *
 * Encapsulates all capabilities needed for configuration handling for a
 * specific configuration object, including support for runtime overrides. The
 * overrides are handled on top of the stored configuration so they are not
 * saved back to storage.
 *
 * @ingroup config_api
 */
class Config extends Storable_Config_Base
{
    /**
     * The current runtime data.
     *
     * The configuration data from storage merged with module and settings
     * overrides.
     *
     * @var array
     */
    protected $overridden_data;
    /**
     * The current module overrides.
     *
     * @var array
     */
    protected $module_overrides;
    /**
     * The current settings overrides.
     *
     * @var array
     */
    protected $settings_overrides;
    /**
     * Constructs a configuration object.
     *
     * @param string $name
     *   The name of the configuration object being constructed.
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   A storage object to use for reading and writing the
     *   configuration data.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   An event dispatcher instance to use for configuration events.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
     *   The typed configuration manager service.
     */
    public function __construct($name, Storage_Interface $storage, protected \Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface $event_dispatcher, Typed_Config_Manager_Interface $typed_config)
    {
        $this->name = $name;
        $this->storage = $storage;
        $this->typed_config_manager = $typed_config;
    }
    /**
     * {@inheritdoc}
     */
    public function init_with_data(array $data): static
    {
        parent::init_with_data($data);
        $this->reset_overridden_data();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get($key = '')
    {
        if (!isset($this->overridden_data)) {
            $this->set_overridden_data();
        }
        if (empty($key)) {
            return $this->overridden_data;
        }
        $parts = explode('.', $key);
        if (count($parts) == 1) {
            return $this->overridden_data[$key] ?? null;
        }
        $value = Nested_Array::get_value($this->overridden_data, $parts, $key_exists);
        return $key_exists ? $value : null;
    }
    /**
     * {@inheritdoc}
     */
    public function set_data(array $data): static
    {
        parent::set_data($data);
        $this->reset_overridden_data();
        return $this;
    }
    /**
     * Sets settings.php overrides for this configuration object.
     *
     * The overridden data only applies to this configuration object.
     *
     * @param array $data
     *   The overridden values of the configuration data.
     *
     * @return $this
     *   The configuration object.
     */
    public function set_settings_override(array $data): static
    {
        $this->settings_overrides = $data;
        $this->reset_overridden_data();
        return $this;
    }
    /**
     * Sets module overrides for this configuration object.
     *
     * @param array $data
     *   The overridden values of the configuration data.
     *
     * @return $this
     *   The configuration object.
     */
    public function set_module_override(array $data): static
    {
        $this->module_overrides = $data;
        $this->reset_overridden_data();
        return $this;
    }
    /**
     * Sets the current data for this configuration object.
     *
     * Configuration overrides operate at two distinct layers: modules and
     * settings.php. Overrides in settings.php take precedence over values
     * provided by modules. Precedence or different module overrides is
     * determined by the priority of the config.factory.override tagged services.
     *
     * @return $this
     *   The configuration object.
     */
    protected function set_overridden_data(): static
    {
        $this->overridden_data = $this->data;
        if (isset($this->module_overrides) && is_array($this->module_overrides)) {
            $this->overridden_data = Nested_Array::merge_deep_array([$this->overridden_data, $this->module_overrides], true);
        }
        if (isset($this->settings_overrides) && is_array($this->settings_overrides)) {
            $this->overridden_data = Nested_Array::merge_deep_array([$this->overridden_data, $this->settings_overrides], true);
        }
        return $this;
    }
    /**
     * Resets the current data, so overrides are re-applied.
     *
     * This method should be called after the original data or the overridden data
     * has been changed.
     *
     * @return $this
     *   The configuration object.
     */
    protected function reset_overridden_data(): static
    {
        unset($this->overridden_data);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function set($key, $value): static
    {
        parent::set($key, $value);
        $this->reset_overridden_data();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function clear($key): static
    {
        parent::clear($key);
        $this->reset_overridden_data();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function save($has_trusted_data = false): static
    {
        // Validate the configuration object name before saving.
        static::validate_name($this->name);
        // If there is a schema for this configuration object, cast all values to
        // conform to the schema.
        if (!$has_trusted_data) {
            if ($this->typed_config_manager->has_config_schema($this->name)) {
                // Ensure that the schema wrapper has the latest data.
                $this->schema_wrapper = null;
                $this->data = $this->cast_value(null, $this->data);
                // Reclaim the memory used by the schema wrapper.
                $this->schema_wrapper = null;
            } else {
                foreach ($this->data as $key => $value) {
                    $this->validate_value($key, $value);
                }
            }
        }
        // Potentially configuration schema could have changed the underlying data's
        // types.
        $this->reset_overridden_data();
        $this->storage->write($this->name, $this->data);
        if (!$this->is_new) {
            Cache::invalidate_tags($this->get_cache_tags());
        }
        $this->is_new = false;
        $event_name = $this->get_storage()->get_collection_name() === Storage_Interface::DEFAULT_COLLECTION ? Config_Events::SAVE : Config_Collection_Events::SAVE_IN_COLLECTION;
        $this->event_dispatcher->dispatch(new Config_Crud_Event($this), $event_name);
        $this->original_data = $this->data;
        return $this;
    }
    /**
     * Deletes the configuration object.
     *
     * @return $this
     *   The configuration object.
     */
    public function delete(): static
    {
        $this->data = [];
        $this->storage->delete($this->name);
        Cache::invalidate_tags($this->get_cache_tags());
        $this->is_new = true;
        $this->reset_overridden_data();
        $event_name = $this->get_storage()->get_collection_name() === Storage_Interface::DEFAULT_COLLECTION ? Config_Events::DELETE : Config_Collection_Events::DELETE_IN_COLLECTION;
        $this->event_dispatcher->dispatch(new Config_Crud_Event($this), $event_name);
        $this->original_data = $this->data;
        return $this;
    }
    /**
     * Gets original data from this configuration object.
     *
     * Original data is the data as it is immediately after loading from
     * configuration storage before any changes. If this is a new configuration
     * object it will be an empty array.
     *
     * @param string $key
     *   A string that maps to a key within the configuration data.
     * @param bool $apply_overrides
     *   Apply any overrides to the original data. Defaults to TRUE.
     *
     * @return mixed
     *   The data that was requested.
     *
     * @see \Drupal\Core\Config\Config::get()
     */
    public function get_original($key = '', $apply_overrides = true)
    {
        $original_data = $this->original_data;
        if ($apply_overrides) {
            // Apply overrides.
            if (isset($this->module_overrides) && is_array($this->module_overrides)) {
                $original_data = Nested_Array::merge_deep_array([$original_data, $this->module_overrides], true);
            }
            if (isset($this->settings_overrides) && is_array($this->settings_overrides)) {
                $original_data = Nested_Array::merge_deep_array([$original_data, $this->settings_overrides], true);
            }
        }
        if (empty($key)) {
            return $original_data;
        }
        $parts = explode('.', $key);
        if (count($parts) == 1) {
            return $original_data[$key] ?? null;
        }
        $value = Nested_Array::get_value($original_data, $parts, $key_exists);
        return $key_exists ? $value : null;
    }
    /**
     * Determines if overrides are applied to a key for this configuration object.
     *
     * @param string $key
     *   (optional) A string that maps to a key within the configuration data.
     *   For instance in the following configuration array:
     *   @code
     *   [
     *     'foo' => [
     *       'bar' => 'baz',
     *     ],
     *   ];
     *   @endcode
     *   A key of 'foo.bar' would map to the string 'baz'. However, a key of 'foo'
     *   would map to the ['bar' => 'baz'].
     *   If not supplied TRUE will be returned if there are any overrides at all
     *   for this configuration object.
     *
     * @return bool
     *   TRUE if there are any overrides for the key, otherwise FALSE.
     */
    public function has_overrides($key = ''): ?bool
    {
        if (empty($key)) {
            return !(empty($this->module_overrides) && empty($this->settings_overrides));
        }
        $parts = explode('.', $key);
        $override_exists = false;
        if (isset($this->module_overrides) && is_array($this->module_overrides)) {
            $override_exists = Nested_Array::key_exists($this->module_overrides, $parts);
        }
        if (!$override_exists && isset($this->settings_overrides) && is_array($this->settings_overrides)) {
            return Nested_Array::key_exists($this->settings_overrides, $parts);
        }
        return $override_exists;
    }
}