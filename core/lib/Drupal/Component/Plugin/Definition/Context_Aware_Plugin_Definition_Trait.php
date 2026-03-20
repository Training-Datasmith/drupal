<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Definition;

use Drupal\Component\Plugin\Context\Context_Definition_Interface;
use Drupal\Component\Plugin\Exception\Context_Exception;
/**
 * Provides a trait for context-aware object-based plugin definitions.
 */
trait Context_Aware_Plugin_Definition_Trait
{
    /**
     * The context definitions for this plugin definition.
     *
     * @var \Drupal\Component\Plugin\Context\ContextDefinitionInterface[]
     */
    protected $context_definitions = [];
    /**
     * Implements \Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface::hasContextDefinition().
     */
    public function has_context_definition($name): bool
    {
        return array_key_exists($name, $this->context_definitions);
    }
    /**
     * Implements \Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface::getContextDefinitions().
     */
    public function get_context_definitions()
    {
        return $this->context_definitions;
    }
    /**
     * Implements \Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface::getContextDefinition().
     */
    public function get_context_definition($name)
    {
        if ($this->has_context_definition($name)) {
            return $this->context_definitions[$name];
        }
        throw new Context_Exception($this->id() . " does not define a '{$name}' context");
    }
    /**
     * Implements \Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface::addContextDefinition().
     */
    public function add_context_definition($name, Context_Definition_Interface $definition)
    {
        $this->context_definitions[$name] = $definition;
        return $this;
    }
    /**
     * Implements \Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionInterface::removeContextDefinition().
     */
    public function remove_context_definition($name)
    {
        unset($this->context_definitions[$name]);
        return $this;
    }
}