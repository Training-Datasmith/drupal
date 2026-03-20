<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Render\Markup_Interface;
use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Interface;
use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Trait;
use Drupal\Core\Dependency_Injection\Dependency_Serialization_Trait;
/**
 * Provides a base class for configuration objects with get/set support.
 *
 * Encapsulates all capabilities needed for runtime configuration handling for
 * a specific configuration object.
 *
 * Extend directly from this class for non-storable configuration where the
 * configuration API is desired but storage is not possible; for example, if
 * the data is derived at runtime. For storable configuration, extend
 * \Drupal\Core\Config\StorableConfigBase.
 *
 * @see \Drupal\Core\Config\StorableConfigBase
 * @see \Drupal\Core\Config\Config
 * @see \Drupal\Core\Theme\ThemeSettings
 */
abstract class Config_Base implements Refinable_Cacheable_Dependency_Interface
{
    use Dependency_Serialization_Trait;
    use Refinable_Cacheable_Dependency_Trait;
    /**
     * The name of the configuration object.
     *
     * @var string
     */
    protected $name;
    /**
     * The data of the configuration object.
     *
     * @var array
     */
    protected $data = [];
    /**
     * The maximum length of a configuration object name.
     *
     * Many filesystems (including HFS, NTFS, and ext4) have a maximum file name
     * length of 255 characters. To ensure that no configuration objects
     * incompatible with this limitation are created, we enforce a maximum name
     * length of 250 characters (leaving 5 characters for the file extension).
     *
     * @see http://wikipedia.org/wiki/Comparison_of_file_systems
     *
     * Configuration objects not stored on the filesystem should still be
     * restricted in name length so name can be used as a cache key.
     */
    public const MAX_NAME_LENGTH = 250;
    /**
     * Returns the name of this configuration object.
     *
     * @return string
     *   The name of the configuration object.
     */
    public function get_name()
    {
        return $this->name;
    }
    /**
     * Sets the name of this configuration object.
     *
     * @param string $name
     *   The name of the configuration object.
     *
     * @return $this
     *   The configuration object.
     */
    public function set_name($name)
    {
        $this->name = $name;
        return $this;
    }
    /**
     * Validates the configuration object name.
     *
     * @param string $name
     *   The name of the configuration object.
     *
     * @throws \Drupal\Core\Config\ConfigNameException
     *
     * @see Config::MAX_NAME_LENGTH
     */
    public static function validate_name($name): void
    {
        // The name must be namespaced by owner.
        if (!str_contains($name, '.')) {
            throw new Config_Name_Exception("Missing namespace in Config object name {$name}.");
        }
        // The name must be shorter than Config::MAX_NAME_LENGTH characters.
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            throw new Config_Name_Exception("Config object name {$name} exceeds maximum allowed length of " . static::MAX_NAME_LENGTH . ' characters.');
        }
        // The name must not contain any of the following characters:
        // : ? * < > ' " / \
        if (preg_match('/[:?*<>"\'\/\\\\]/', $name)) {
            throw new Config_Name_Exception("Invalid character in Config object name {$name}.");
        }
    }
    /**
     * Gets data from this configuration object.
     *
     * @param string $key
     *   A string that maps to a key within the configuration data.
     *   For instance in the following configuration array:
     *   @code
     *   [
     *     'foo' => [
     *       'bar' => 'baz',
     *     ],
     *   ];
     *   @endcode
     *   A key of 'foo.bar' would return the string 'baz'. However, a key of 'foo'
     *   would return ['bar' => 'baz'].
     *   If no key is specified, then the entire data array is returned.
     *
     * @return mixed
     *   The data that was requested.
     */
    public function get($key = '')
    {
        if (empty($key)) {
            return $this->data;
        }
        $parts = explode('.', $key);
        if (count($parts) == 1) {
            return $this->data[$key] ?? null;
        }
        $value = Nested_Array::get_value($this->data, $parts, $key_exists);
        return $key_exists ? $value : null;
    }
    /**
     * Replaces the data of this configuration object.
     *
     * @param array $data
     *   The new configuration data.
     *
     * @return $this
     *   The configuration object.
     *
     * @throws \Drupal\Core\Config\ConfigValueException
     *   If any key in $data in any depth contains a dot.
     */
    public function set_data(array $data)
    {
        $data = $this->cast_safe_strings($data);
        $this->validate_keys($data);
        $this->data = $data;
        return $this;
    }
    /**
     * Sets a value in this configuration object.
     *
     * @param string $key
     *   Identifier to store value in configuration.
     * @param mixed $value
     *   Value to associate with identifier.
     *
     * @return $this
     *   The configuration object.
     *
     * @throws \Drupal\Core\Config\ConfigValueException
     *   If $value is an array and any of its keys in any depth contains a dot.
     */
    public function set($key, $value)
    {
        $value = $this->cast_safe_strings($value);
        // The dot/period is a reserved character; it may appear between keys, but
        // not within keys.
        if (is_array($value)) {
            $this->validate_keys($value);
        }
        $parts = explode('.', $key);
        if (count($parts) == 1) {
            $this->data[$key] = $value;
        } else {
            Nested_Array::set_value($this->data, $parts, $value);
        }
        return $this;
    }
    /**
     * Validates all keys in a passed in config array structure.
     *
     * @param array $data
     *   Configuration array structure.
     *
     * @throws \Drupal\Core\Config\ConfigValueException
     *   If any key in $data in any depth contains a dot.
     */
    protected function validate_keys(array $data)
    {
        foreach ($data as $key => $value) {
            if (str_contains((string) $key, '.')) {
                throw new Config_Value_Exception("{$key} key contains a dot which is not supported.");
            }
            if (is_array($value)) {
                $this->validate_keys($value);
            }
        }
    }
    /**
     * Unsets a value in this configuration object.
     *
     * @param string $key
     *   Name of the key whose value should be unset.
     *
     * @return $this
     *   The configuration object.
     */
    public function clear($key)
    {
        $parts = explode('.', $key);
        if (count($parts) == 1) {
            unset($this->data[$key]);
        } else {
            Nested_Array::unset_value($this->data, $parts);
        }
        return $this;
    }
    /**
     * Merges data into a configuration object.
     *
     * @param array $data_to_merge
     *   An array containing data to merge.
     *
     * @return $this
     *   The configuration object.
     */
    public function merge(array $data_to_merge)
    {
        // Preserve integer keys so that configuration keys are not changed.
        $this->set_data(Nested_Array::merge_deep_array([$this->data, $data_to_merge], true));
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_contexts()
    {
        return $this->cache_contexts;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags()
    {
        return Cache::merge_tags(['config:' . $this->name], $this->cache_tags);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_max_age()
    {
        return $this->cache_max_age;
    }
    /**
     * Casts any objects that implement MarkupInterface to string.
     *
     * @param mixed $data
     *   The configuration data.
     *
     * @return mixed
     *   The data with any safe strings cast to string.
     */
    protected function cast_safe_strings($data)
    {
        if ($data instanceof Markup_Interface) {
            $data = (string) $data;
        } elseif (is_array($data)) {
            array_walk_recursive($data, function (&$value): void {
                if ($value instanceof Markup_Interface) {
                    $value = (string) $value;
                }
            });
        }
        return $data;
    }
}