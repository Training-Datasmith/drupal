<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Definition;

use Drupal\Component\Plugin\Context\Context_Definition_Interface;
/**
 * Provides an interface for plugin definitions which use contexts.
 *
 * @ingroup Plugin
 */
interface Context_Aware_Plugin_Definition_Interface extends Plugin_Definition_Interface
{
    /**
     * Checks if the plugin defines a particular context.
     *
     * @param string $name
     *   The context name.
     *
     * @return bool
     *   TRUE if the plugin defines the given context, otherwise FALSE.
     */
    public function has_context_definition($name);
    /**
     * Returns all context definitions for this plugin.
     *
     * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface[]
     *   The context definitions.
     */
    public function get_context_definitions();
    /**
     * Returns a particular context definition for this plugin.
     *
     * @param string $name
     *   The context name.
     *
     * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface
     *   The context definition.
     *
     * @throws \Drupal\Component\Plugin\Exception\ContextException
     *   Thrown if the plugin does not define the given context.
     */
    public function get_context_definition($name);
    /**
     * Adds a context to this plugin definition.
     *
     * @param string $name
     *   The context name.
     * @param \Drupal\Component\Plugin\Context\ContextDefinitionInterface $definition
     *   The context definition.
     *
     * @return $this
     *   The called object.
     */
    public function add_context_definition($name, Context_Definition_Interface $definition);
    /**
     * Removes a context definition from this plugin.
     *
     * @param string $name
     *   The context name.
     *
     * @return $this
     *   The called object.
     */
    public function remove_context_definition($name);
}