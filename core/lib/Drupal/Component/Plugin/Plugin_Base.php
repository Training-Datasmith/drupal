<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

/**
 * Base class for plugins wishing to support metadata inspection.
 */
abstract class Plugin_Base implements Plugin_Inspection_Interface, Derivative_Inspection_Interface
{
    /**
     * A string which is used to separate base plugin IDs from the derivative ID.
     */
    public const DERIVATIVE_SEPARATOR = ':';
    /**
     * Constructs a \Drupal\Component\Plugin\PluginBase object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $pluginId
     *   The plugin ID for the plugin instance.
     * @param mixed $pluginDefinition
     *   The plugin implementation definition.
     */
    public function __construct(
        protected array $configuration,
        /**
         * The plugin ID.
         */
        protected $plugin_id,
        /**
         * The plugin implementation definition.
         */
        protected $plugin_definition
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_plugin_id()
    {
        return $this->plugin_id;
    }
    /**
     * {@inheritdoc}
     */
    public function get_base_id()
    {
        $plugin_id = $this->get_plugin_id();
        if (strpos($plugin_id, (string) static::DERIVATIVE_SEPARATOR)) {
            [$plugin_id] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
        }
        return $plugin_id;
    }
    /**
     * {@inheritdoc}
     */
    public function get_derivative_id()
    {
        $plugin_id = $this->get_plugin_id();
        $derivative_id = null;
        if (strpos($plugin_id, (string) static::DERIVATIVE_SEPARATOR)) {
            [, $derivative_id] = explode(static::DERIVATIVE_SEPARATOR, $plugin_id, 2);
        }
        return $derivative_id;
    }
    /**
     * {@inheritdoc}
     */
    public function get_plugin_definition()
    {
        return $this->plugin_definition;
    }
}