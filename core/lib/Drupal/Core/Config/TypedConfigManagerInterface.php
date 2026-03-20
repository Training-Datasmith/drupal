<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Typed_Data\Typed_Data_Manager_Interface;
/**
 * Defines an interface for managing config schema type plugins.
 *
 * @see \Drupal\Core\Config\TypedConfigManager
 * @see \Drupal\Core\Config\Schema\ConfigSchemaDiscovery
 * @see hook_config_schema_info_alter()
 * @see https://www.drupal.org/node/1905070
 */
interface Typed_Config_Manager_Interface extends Typed_Data_Manager_Interface
{
    /**
     * Gets typed configuration data.
     *
     * @param string $name
     *   Configuration object name.
     *
     * @return \Drupal\Core\TypedData\TraversableTypedDataInterface
     *   Typed configuration element.
     */
    public function get($name);
    /**
     * Creates a new data definition object.
     *
     * Since type definitions may contain variables to be replaced, we need the
     * configuration value to create it.
     *
     * @param array $definition
     *   The base type definition array, for which a data definition should be
     *   created.
     * @param mixed $value
     *   Optional value of the configuration element.
     * @param string $name
     *   Optional name of the configuration element.
     * @param object $parent
     *   Optional parent element.
     *
     * @return \Drupal\Core\TypedData\DataDefinitionInterface
     *   A data definition for the given data type.
     */
    public function build_data_definition(array $definition, $value, $name = null, $parent = null);
    /**
     * Checks if the configuration schema with the given config name exists.
     *
     * @param string $name
     *   Configuration name.
     *
     * @return bool
     *   TRUE if configuration schema exists, FALSE otherwise.
     */
    public function has_config_schema($name);
    /**
     * Gets a specific plugin definition.
     *
     * @param string $plugin_id
     *   A plugin id.
     * @param bool $exception_on_invalid
     *   Ignored with TypedConfigManagerInterface. Kept for compatibility with
     *   DiscoveryInterface.
     *
     * @return array
     *   A plugin definition array. If the given plugin id does not have typed
     *   configuration definition assigned, the definition of an undefined
     *   element type is returned.
     */
    public function get_definition($plugin_id, $exception_on_invalid = true);
    /**
     * Gets typed data for a given configuration name and its values.
     *
     * @param string $config_name
     *   The machine name of the configuration.
     * @param array $config_data
     *   The data associated with the configuration. Note: This configuration
     *   doesn't yet have to be stored.
     *
     * @return \Drupal\Core\TypedData\TraversableTypedDataInterface
     *   The typed configuration element.
     */
    public function create_from_name_and_data($config_name, array $config_data);
}