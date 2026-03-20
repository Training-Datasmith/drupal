<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin;

use Drupal\Component\Plugin\Context\Context_Interface;
/**
 * Interface for defining context aware plugins.
 *
 * Context aware plugins can specify an array of context definitions keyed by
 * context name at the plugin definition under the "context" key.
 *
 * @ingroup plugin_api
 */
interface Context_Aware_Plugin_Interface extends Plugin_Inspection_Interface
{
    /**
     * Gets the context definitions of the plugin.
     *
     * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface[]
     *   The array of context definitions, keyed by context name.
     */
    public function get_context_definitions();
    /**
     * Gets a specific context definition of the plugin.
     *
     * @param string $name
     *   The name of the context in the plugin definition.
     *
     * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface
     *   The definition against which the context value must validate.
     *
     * @throws \Drupal\Component\Plugin\Exception\ContextException
     *   If the requested context is not defined.
     */
    public function get_context_definition($name);
    /**
     * Gets the defined contexts.
     *
     * @return array
     *   The set context objects.
     *
     * @throws \Drupal\Component\Plugin\Exception\ContextException
     *   If contexts are defined but not set.
     */
    public function get_contexts();
    /**
     * Gets a defined context.
     *
     * @param string $name
     *   The name of the context in the plugin definition.
     *
     * @return \Drupal\Component\Plugin\Context\ContextInterface
     *   The context object.
     *
     * @throws \Drupal\Component\Plugin\Exception\ContextException
     *   If the requested context is not set.
     */
    public function get_context($name);
    /**
     * Gets the values for all defined contexts.
     *
     * @return array
     *   An array of set context values, keyed by context name. If a context is
     *   unset its value is returned as NULL.
     */
    public function get_context_values();
    /**
     * Gets the value for a defined context.
     *
     * @param string $name
     *   The name of the context in the plugin configuration.
     *
     * @return mixed
     *   The currently set context value.
     *
     * @throws \Drupal\Component\Plugin\Exception\ContextException
     *   If the requested context is not set.
     */
    public function get_context_value($name);
    /**
     * Set a context on this plugin.
     *
     * @param string $name
     *   The name of the context in the plugin configuration.
     * @param \Drupal\Component\Plugin\Context\ContextInterface $context
     *   The context object to set.
     */
    public function set_context($name, Context_Interface $context);
    /**
     * Sets the value for a defined context.
     *
     * @param string $name
     *   The name of the context in the plugin definition.
     * @param mixed $value
     *   The value to set the context to. The value has to validate against the
     *   provided context definition.
     *
     * @return $this
     *   A context aware plugin object for chaining.
     *
     * @throws \Drupal\Component\Plugin\Exception\ContextException
     *   If the value does not pass validation.
     */
    public function set_context_value($name, $value);
    /**
     * Validates the set values for the defined contexts.
     *
     * @return \Symfony\Component\Validator\ConstraintViolationListInterface
     *   A list of constraint violations. If the list is empty, validation
     *   succeeded.
     */
    public function validate_contexts();
    /**
     * Gets a mapping of the expected assignment names to their context names.
     *
     * @return array
     *   A mapping of the expected assignment names to their context names. For
     *   example, if one of the $contexts is named 'user.current_user', but the
     *   plugin expects a context named 'user', then this map would contain
     *   'user' => 'user.current_user'.
     */
    public function get_context_mapping();
    /**
     * Sets a mapping of the expected assignment names to their context names.
     *
     * @param array $context_mapping
     *   A mapping of the expected assignment names to their context names. For
     *   example, if one of the $contexts is named 'user.current_user', but the
     *   plugin expects a context named 'user', then this map would contain
     *   'user' => 'user.current_user'.
     *
     * @return $this
     */
    public function set_context_mapping(array $context_mapping);
}