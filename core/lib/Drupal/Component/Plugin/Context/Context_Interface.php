<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Context;

/**
 * Provides data and definitions for plugins during runtime and administration.
 *
 * Plugin contexts are satisfied by ContextInterface implementing objects.
 * These objects always contain a definition of what data they will provide
 * during runtime. During run time, ContextInterface implementing objects must
 * also provide the corresponding data value.
 *
 * @see \Drupal\Component\Plugin\Context\ContextDefinitionInterface
 */
interface Context_Interface
{
    /**
     * Gets the context value.
     *
     * @return mixed
     *   The currently set context value, or NULL if it is not set.
     */
    public function get_context_value();
    /**
     * Returns whether the context has a value.
     *
     * @return bool
     *   TRUE if the context has a value, FALSE otherwise.
     */
    public function has_context_value();
    /**
     * Gets the provided definition that the context must conform to.
     *
     * @return \Drupal\Component\Plugin\Context\ContextDefinitionInterface
     *   The defining characteristic representation of the context.
     */
    public function get_context_definition();
    /**
     * Gets a list of validation constraints.
     *
     * @return array
     *   Array of constraints, each being an instance of
     *   \Symfony\Component\Validator\Constraint.
     */
    public function get_constraints();
    /**
     * Validates the set context value.
     *
     * @return \Symfony\Component\Validator\ConstraintViolationListInterface
     *   A list of constraint violations. If the list is empty, validation
     *   succeeded.
     */
    public function validate();
}