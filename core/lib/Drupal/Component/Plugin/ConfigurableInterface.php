<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

/**
 * Provides an interface for a configurable plugin.
 *
 * @ingroup plugin_api
 */
interface Configurable_Interface
{
    /**
     * Gets this plugin's configuration.
     *
     * @return array
     *   An array of this plugin's configuration.
     */
    public function get_configuration();
    /**
     * Sets the configuration for this plugin instance.
     *
     * @param array $configuration
     *   An associative array containing the plugin's configuration.
     */
    public function set_configuration(array $configuration);
    /**
     * Gets default configuration for this plugin.
     *
     * @return array
     *   An associative array with the default configuration.
     */
    public function default_configuration();
}