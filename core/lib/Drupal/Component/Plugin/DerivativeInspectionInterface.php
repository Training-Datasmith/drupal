<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

/**
 * Provides a plugin interface for providing derivative metadata inspection.
 */
interface Derivative_Inspection_Interface
{
    /**
     * Gets the base_plugin_id of the plugin instance.
     *
     * @return string
     *   The base_plugin_id of the plugin instance.
     */
    public function get_base_id();
    /**
     * Gets the derivative_id of the plugin instance.
     *
     * @return string|null
     *   The derivative_id of the plugin instance NULL otherwise.
     */
    public function get_derivative_id();
}