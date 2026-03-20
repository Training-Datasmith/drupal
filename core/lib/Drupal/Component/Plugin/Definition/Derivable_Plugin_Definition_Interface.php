<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Definition;

/**
 * Provides an interface for a derivable plugin definition.
 *
 * @see \Drupal\Component\Plugin\Derivative\DeriverInterface
 */
interface Derivable_Plugin_Definition_Interface extends Plugin_Definition_Interface
{
    /**
     * Gets the name of the deriver of this plugin definition, if it exists.
     *
     * @return class-string|null
     *   Either the deriver class name, or NULL if the plugin is not derived.
     */
    public function get_deriver();
    /**
     * Sets the deriver of this plugin definition.
     *
     * @param class-string|null $deriver
     *   Either the name of a class that implements
     *   \Drupal\Component\Plugin\Derivative\DeriverInterface, or NULL.
     *
     * @return $this
     */
    public function set_deriver($deriver);
}