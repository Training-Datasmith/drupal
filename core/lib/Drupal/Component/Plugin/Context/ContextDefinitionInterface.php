<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Context;

/**
 * Interface used to define definition objects found in ContextInterface.
 *
 * @see \Drupal\Component\Plugin\Context\ContextInterface
 *
 * @todo WARNING: This interface is going to receive some additions as part of
 * https://www.drupal.org/node/2346999.
 */
interface Context_Definition_Interface
{
    /**
     * Gets a human readable label.
     *
     * @return string
     *   The label.
     */
    public function get_label();
    /**
     * Sets the human readable label.
     *
     * @param string $label
     *   The label to set.
     *
     * @return $this
     */
    public function set_label($label);
    /**
     * Gets a human readable description.
     *
     * @return string|null
     *   The description, or NULL if no description is available.
     */
    public function get_description();
    /**
     * Sets the human readable description.
     *
     * @param string|null $description
     *   The description to set.
     *
     * @return $this
     */
    public function set_description($description);
    /**
     * Gets the data type needed by the context.
     *
     * If the context is multiple-valued, this represents the type of each value.
     *
     * @return string
     *   The data type.
     */
    public function get_data_type();
    /**
     * Sets the data type needed by the context.
     *
     * @param string $data_type
     *   The data type to set.
     *
     * @return $this
     */
    public function set_data_type($data_type);
    /**
     * Determines whether the data is multi-valued, i.e. a list of data items.
     *
     * @return bool
     *   Whether the data is multi-valued; i.e. a list of data items.
     */
    public function is_multiple();
    /**
     * Sets whether the data is multi-valued.
     *
     * @param bool $multiple
     *   (optional) Whether the data is multi-valued. Defaults to TRUE.
     *
     * @return $this
     */
    public function set_multiple($multiple = true);
    /**
     * Determines whether the context is required.
     *
     * For required data a non-NULL value is mandatory.
     *
     * @return bool
     *   Whether a data value is required.
     */
    public function is_required();
    /**
     * Sets whether the data is required.
     *
     * @param bool $required
     *   (optional) Whether the data is multi-valued. Defaults to TRUE.
     *
     * @return $this
     */
    public function set_required($required = true);
    /**
     * Gets the default value for this context definition.
     *
     * @return mixed
     *   The default value or NULL if no default value is set.
     */
    public function get_default_value();
    /**
     * Sets the default data value.
     *
     * @param mixed $default_value
     *   The default value to be set or NULL to remove any default value.
     *
     * @return $this
     */
    public function set_default_value($default_value);
    /**
     * Gets an array of validation constraints.
     *
     * @return array
     *   An array of validation constraint definitions, keyed by constraint name.
     *   Each constraint definition can be used for instantiating
     *   \Symfony\Component\Validator\Constraint objects.
     */
    public function get_constraints();
    /**
     * Sets the array of validation constraints.
     *
     * NOTE: This will override any previously set constraints. In most cases
     * ContextDefinitionInterface::addConstraint() should be used instead.
     *
     * @param array $constraints
     *   The array of constraints.
     *
     * @return $this
     *
     * @see self::addConstraint()
     */
    public function set_constraints(array $constraints);
    /**
     * Adds a validation constraint.
     *
     * @param string $constraint_name
     *   The name of the constraint to add, i.e. its plugin id.
     * @param array|null $options
     *   The constraint options as required by the constraint plugin, or NULL.
     *
     * @return $this
     */
    public function add_constraint(string $constraint_name, ?array $options = null): static;
    /**
     * Gets a validation constraint.
     *
     * @param string $constraint_name
     *   The name of the constraint, i.e. its plugin id.
     *
     * @return array
     *   A validation constraint definition which can be used for instantiating a
     *   \Symfony\Component\Validator\Constraint object.
     */
    public function get_constraint($constraint_name);
}