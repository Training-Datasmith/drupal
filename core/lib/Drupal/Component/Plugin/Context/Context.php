<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Context;

use Drupal\Component\Plugin\Exception\Context_Exception;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;
/**
 * A generic context class for wrapping data a plugin needs to operate.
 */
class Context implements Context_Interface
{
    /**
     * Create a context object.
     *
     * @param \Drupal\Component\Plugin\Context\ContextDefinitionInterface $contextDefinition
     *   The context definition.
     * @param mixed|null $contextValue
     *   The value of the context.
     */
    public function __construct(
        protected \Drupal\Component\Plugin\Context\Context_Definition_Interface $context_definition,
        /**
         * The value of the context.
         */
        protected $context_value = null
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_context_value()
    {
        // Support optional contexts.
        if (!isset($this->context_value)) {
            $definition = $this->get_context_definition();
            $default_value = $definition->get_default_value();
            if (!isset($default_value) && $definition->is_required()) {
                $type = $definition->get_data_type();
                throw new Context_Exception(sprintf('The %s context is required and not present.', $type));
            }
            // Keep the default value here so that subsequent calls don't have to look
            // it up again.
            $this->context_value = $default_value;
        }
        return $this->context_value;
    }
    /**
     * {@inheritdoc}
     */
    public function has_context_value(): bool
    {
        return $this->context_value !== null || $this->get_context_definition()->get_default_value() !== null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_context_definition()
    {
        return $this->context_definition;
    }
    /**
     * {@inheritdoc}
     */
    public function get_constraints(): array
    {
        if (empty($this->context_definition['class'])) {
            throw new Context_Exception('An error was encountered while trying to validate the context.');
        }
        return [new Type($this->context_definition['class'])];
    }
    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        $validator = Validation::create_validator_builder()->get_validator();
        return $validator->validate_value($this->get_context_value(), $this->get_constraints());
    }
}