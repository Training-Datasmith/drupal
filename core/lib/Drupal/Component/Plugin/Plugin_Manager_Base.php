<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

use Drupal\Component\Plugin\Discovery\Discovery_Trait;
use Drupal\Component\Plugin\Exception\Plugin_Not_Found_Exception;
/**
 * Base class for plugin managers.
 */
abstract class Plugin_Manager_Base implements Plugin_Manager_Interface
{
    use Discovery_Trait;
    /**
     * The object that discovers plugins managed by this manager.
     *
     * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface
     */
    protected $discovery;
    /**
     * The object that instantiates plugins managed by this manager.
     *
     * @var \Drupal\Component\Plugin\Factory\FactoryInterface
     */
    protected $factory;
    /**
     * The preconfigured plugin instance for a particular runtime condition.
     *
     * @var \Drupal\Component\Plugin\Mapper\MapperInterface|null
     */
    protected $mapper;
    /**
     * Gets the plugin discovery.
     *
     * @return \Drupal\Component\Plugin\Discovery\DiscoveryInterface
     *   The plugin discovery.
     */
    protected function get_discovery()
    {
        return $this->discovery;
    }
    /**
     * Gets the plugin factory.
     *
     * @return \Drupal\Component\Plugin\Factory\FactoryInterface
     *   The plugin factory.
     */
    protected function get_factory()
    {
        return $this->factory;
    }
    /**
     * {@inheritdoc}
     */
    public function get_definition($plugin_id, $exception_on_invalid = true)
    {
        return $this->get_discovery()->get_definition($plugin_id, $exception_on_invalid);
    }
    /**
     * {@inheritdoc}
     */
    public function get_definitions()
    {
        return $this->get_discovery()->get_definitions();
    }
    /**
     * {@inheritdoc}
     */
    public function create_instance($plugin_id, array $configuration = [])
    {
        // If this PluginManager has fallback capabilities catch
        // PluginNotFoundExceptions.
        if ($this instanceof Fallback_Plugin_Manager_Interface) {
            try {
                return $this->get_factory()->create_instance($plugin_id, $configuration);
            } catch (Plugin_Not_Found_Exception) {
                return $this->handle_plugin_not_found($plugin_id, $configuration);
            }
        } else {
            return $this->get_factory()->create_instance($plugin_id, $configuration);
        }
    }
    /**
     * Allows plugin managers to specify custom behavior if a plugin is not found.
     *
     * @param string $plugin_id
     *   The ID of the missing requested plugin.
     * @param array $configuration
     *   An array of configuration relevant to the plugin instance.
     *
     * @return object
     *   A fallback plugin instance.
     *
     * @throws \BadMethodCallException
     *   When ::getFallbackPluginId() is not implemented in the concrete plugin
     *   manager class.
     */
    protected function handle_plugin_not_found($plugin_id, array $configuration)
    {
        $fallback_id = $this->get_fallback_plugin_id($plugin_id, $configuration);
        return $this->get_factory()->create_instance($fallback_id, $configuration);
    }
    /**
     * Gets a fallback id for a missing plugin.
     *
     * This method should be implemented in extending classes that also implement
     * FallbackPluginManagerInterface. It is called by
     * PluginManagerBase::handlePluginNotFound on the abstract class, and
     * therefore should be defined as well on the abstract class to prevent static
     * analysis errors.
     *
     * @param string $plugin_id
     *   The ID of the missing requested plugin.
     * @param array $configuration
     *   An array of configuration relevant to the plugin instance.
     *
     * phpcs:ignore Drupal.Commenting.FunctionComment.InvalidNoReturn
     * @return string
     *   The id of an existing plugin to use when the plugin does not exist.
     *
     * @throws \BadMethodCallException
     *   If the method is not implemented in the concrete plugin manager class.
     */
    protected function get_fallback_plugin_id($plugin_id, array $configuration = [])
    {
        throw new \BadMethodCallException(static::class . '::getFallbackPluginId() not implemented.');
    }
    /**
     * {@inheritdoc}
     */
    public function get_instance(array $options)
    {
        if (!$this->mapper) {
            throw new \BadMethodCallException(sprintf('%s does not support this method unless %s::$mapper is set.', static::class, static::class));
        }
        return $this->mapper->get_instance($options);
    }
}