<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

/**
 * Defines an object which stores multiple plugin instances to lazy load them.
 *
 * @ingroup plugin_api
 */
abstract class Lazy_Plugin_Collection implements \IteratorAggregate, \Countable
{
    /**
     * Stores all instantiated plugins.
     *
     * @var array
     */
    protected $plugin_instances = [];
    /**
     * Stores the IDs of all potential plugin instances.
     *
     * @var array
     */
    protected $instance_ids = [];
    /**
     * Initializes and stores a plugin.
     *
     * @param string $instance_id
     *   The ID of the plugin instance to initialize.
     */
    abstract protected function initialize_plugin($instance_id);
    /**
     * Gets the current configuration of all plugins in this collection.
     *
     * @return array
     *   An array of up-to-date plugin configuration.
     */
    abstract public function get_configuration();
    /**
     * Sets the configuration for all plugins in this collection.
     *
     * @param array $configuration
     *   An array of up-to-date plugin configuration.
     *
     * @return $this
     */
    abstract public function set_configuration(array $configuration);
    /**
     * Clears all instantiated plugins.
     */
    public function clear(): void
    {
        $this->plugin_instances = [];
    }
    /**
     * Determines if a plugin instance exists.
     *
     * @param string $instance_id
     *   The ID of the plugin instance to check.
     *
     * @return bool
     *   TRUE if the plugin instance exists, FALSE otherwise.
     */
    public function has($instance_id)
    {
        return isset($this->plugin_instances[$instance_id]) || isset($this->instance_ids[$instance_id]);
    }
    /**
     * Gets a plugin instance, initializing it if necessary.
     *
     * @param string $instance_id
     *   The ID of the plugin instance being retrieved.
     */
    public function &get($instance_id)
    {
        if (!isset($this->plugin_instances[$instance_id])) {
            $this->initialize_plugin($instance_id);
        }
        return $this->plugin_instances[$instance_id];
    }
    /**
     * Stores an initialized plugin.
     *
     * @param string $instance_id
     *   The ID of the plugin instance being stored.
     * @param mixed $value
     *   An instantiated plugin.
     */
    public function set($instance_id, $value): void
    {
        $this->plugin_instances[$instance_id] = $value;
        $this->add_instance_id($instance_id);
    }
    /**
     * Removes an initialized plugin.
     *
     * The plugin can still be used; it will be reinitialized.
     *
     * @param string $instance_id
     *   The ID of the plugin instance to remove.
     */
    public function remove($instance_id): void
    {
        unset($this->plugin_instances[$instance_id]);
    }
    /**
     * Adds an instance ID to the available instance IDs.
     *
     * @param string $id
     *   The ID of the plugin instance to add.
     * @param array|null $configuration
     *   (optional) The configuration used by this instance. Defaults to NULL.
     */
    public function add_instance_id($id, $configuration = null): void
    {
        if (!isset($this->instance_ids[$id])) {
            $this->instance_ids[$id] = $id;
        }
    }
    /**
     * Gets all instance IDs.
     *
     * @return array
     *   An array of all available instance IDs.
     */
    public function get_instance_ids()
    {
        return $this->instance_ids;
    }
    /**
     * Removes an instance ID.
     *
     * @param string $instance_id
     *   The ID of the plugin instance to remove.
     */
    public function remove_instance_id($instance_id): void
    {
        unset($this->instance_ids[$instance_id]);
        $this->remove($instance_id);
    }
    /**
     * @return \Traversable<string, mixed>
     *   A traversable generator.
     */
    public function getIterator(): \Traversable
    {
        $instances = [];
        foreach ($this->get_instance_ids() as $instance_id) {
            $instances[$instance_id] = $this->get($instance_id);
        }
        return new \ArrayIterator($instances);
    }
    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->instance_ids);
    }
}