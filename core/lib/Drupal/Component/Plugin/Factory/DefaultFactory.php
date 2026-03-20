<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Factory;

use Drupal\Component\Plugin\Definition\Plugin_Definition_Interface;
use Drupal\Component\Plugin\Exception\Plugin_Exception;
/**
 * Default plugin factory.
 *
 * Instantiates plugin instances by passing the full configuration array as a
 * single constructor argument. Plugin types wanting to support plugin classes
 * with more flexible constructor signatures can do so by using an alternate
 * factory such as Drupal\Component\Plugin\Factory\ReflectionFactory.
 */
class Default_Factory implements Factory_Interface
{
    /**
     * Constructs a Drupal\Component\Plugin\Factory\DefaultFactory object.
     *
     * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
     *   The plugin discovery.
     * @param string|null $interface
     *   (optional) The interface each plugin should implement.
     */
    public function __construct(
        protected \Drupal\Component\Plugin\Discovery\Discovery_Interface $discovery,
        /**
         * Defines an interface each plugin should implement.
         */
        protected $interface = null
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function create_instance($plugin_id, array $configuration = [])
    {
        $plugin_definition = $this->discovery->get_definition($plugin_id);
        $plugin_class = static::get_plugin_class($plugin_id, $plugin_definition, $this->interface);
        return new $plugin_class($configuration, $plugin_id, $plugin_definition);
    }
    /**
     * Finds the class relevant for a given plugin.
     *
     * @param string $plugin_id
     *   The id of a plugin.
     * @param \Drupal\Component\Plugin\Definition\PluginDefinitionInterface|mixed[] $plugin_definition
     *   The plugin definition associated with the plugin ID.
     * @param string $required_interface
     *   (optional) The required plugin interface.
     *
     * @return string
     *   The appropriate class name.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     *   Thrown when there is no class specified, the class doesn't exist, or
     *   the class does not implement the specified required interface.
     */
    public static function get_plugin_class($plugin_id, $plugin_definition = null, $required_interface = null): string
    {
        $missing_class_message = sprintf('The plugin (%s) did not specify an instance class.', $plugin_id);
        if (is_array($plugin_definition)) {
            if (empty($plugin_definition['class'])) {
                throw new Plugin_Exception($missing_class_message);
            }
            $class = $plugin_definition['class'];
        } elseif ($plugin_definition instanceof Plugin_Definition_Interface) {
            if (!$plugin_definition->get_class()) {
                throw new Plugin_Exception($missing_class_message);
            }
            $class = $plugin_definition->get_class();
        } else {
            $plugin_definition_type = get_debug_type($plugin_definition);
            throw new Plugin_Exception(sprintf('%s can only handle plugin definitions that are arrays or that implement %s, but %s given.', self::class, Plugin_Definition_Interface::class, $plugin_definition_type));
        }
        if (!class_exists($class)) {
            throw new Plugin_Exception(sprintf('Plugin (%s) instance class "%s" does not exist.', $plugin_id, $class));
        }
        if ($required_interface && !is_subclass_of($class, $required_interface)) {
            throw new Plugin_Exception(sprintf('Plugin "%s" (%s) must implement interface %s.', $plugin_id, $class, $required_interface));
        }
        return $class;
    }
}