<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

/**
 * Provides an interface for objects that depend on a plugin.
 */
interface Plugin_Aware_Interface
{
    /**
     * Sets the plugin for this object.
     *
     * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
     *   The plugin.
     */
    public function set_plugin(Plugin_Inspection_Interface $plugin);
}