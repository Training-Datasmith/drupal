<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Render\Formattable_Markup;
/**
 * An exception thrown if configuration with the same name already exists.
 */
class Pre_Existing_Config_Exception extends Config_Exception
{
    /**
     * A list of configuration objects that already exist in active configuration.
     *
     * @var array
     */
    protected $config_objects = [];
    /**
     * The name of the module that is being installed.
     *
     * @var string
     */
    protected $extension;
    /**
     * Gets the list of configuration objects that already exist.
     *
     * @return array
     *   A list of configuration objects that already exist in active
     *   configuration keyed by collection.
     */
    public function get_config_objects()
    {
        return $this->config_objects;
    }
    /**
     * Gets the name of the extension that is being installed.
     *
     * @return string
     *   The name of the extension that is being installed.
     */
    public function get_extension()
    {
        return $this->extension;
    }
    /**
     * Creates an exception for an extension and a list of configuration objects.
     *
     * @param string $extension
     *   The name of the extension that is being installed.
     * @param array $config_objects
     *   A list of configuration objects that already exist in active
     *   configuration, keyed by config collection.
     *
     * @return $this
     */
    public static function create($extension, array $config_objects): static
    {
        $message = new Formattable_Markup('Configuration objects (@config_names) provided by @extension already exist in active configuration', ['@config_names' => implode(', ', static::flatten_config_objects($config_objects)), '@extension' => $extension]);
        $e = new static($message);
        $e->config_objects = $config_objects;
        $e->extension = $extension;
        return $e;
    }
    /**
     * Flattens the config object array to a single dimensional list.
     *
     * @param array $config_objects
     *   A list of configuration objects that already exist in active
     *   configuration, keyed by config collection.
     *
     * @return array
     *   A list of configuration objects that have been prefixed with their
     *   collection.
     */
    public static function flatten_config_objects(array $config_objects): array
    {
        $flat_config_objects = [];
        foreach ($config_objects as $collection => $config_names) {
            $config_names = array_map(function (string $config_name) use ($collection): string {
                if ($collection != Storage_Interface::DEFAULT_COLLECTION) {
                    return str_replace('.', DIRECTORY_SEPARATOR, $collection) . DIRECTORY_SEPARATOR . $config_name;
                }
                return $config_name;
            }, $config_names);
            $flat_config_objects[] = $config_names;
        }
        return array_merge(...$flat_config_objects);
    }
}