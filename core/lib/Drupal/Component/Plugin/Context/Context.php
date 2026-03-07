<?php

declare(strict_types=1);

namespace Drupal\Component\Plugin\Context;

use Drupal\Component\Plugin\Exception\ContextException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * A generic context class for wrapping data a plugin needs to operate.
 */
class Context implements ContextInterface
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
        protected \Drupal\Component\Plugin\Context\ContextDefinitionInterface $contextDefinition,
        /**
         * The value of the context.
         */
        protected $contextValue = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getContextValue()
    {
        // Support optional contexts.
        if (!isset($this->contextValue)) {
            $definition = $this->getContextDefinition();
            $default_value = $definition->getDefaultValue();

            if (!isset($default_value) && $definition->isRequired()) {
                $type = $definition->getDataType();
                throw new ContextException(sprintf('The %s context is required and not present.', $type));
            }
            // Keep the default value here so that subsequent calls don't have to look
            // it up again.
            $this->contextValue = $default_value;
        }
        return $this->contextValue;
    }

    /**
     * {@inheritdoc}
     */
    public function hasContextValue(): bool
    {
        return $this->contextValue !== null || $this->getContextDefinition()->getDefaultValue() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getContextDefinition()
    {
        return $this->contextDefinition;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints(): array
    {
        if (empty($this->contextDefinition['class'])) {
            throw new ContextException('An error was encountered while trying to validate the context.');
        }
        return [new Type($this->contextDefinition['class'])];
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        $validator = Validation::createValidatorBuilder()
          ->getValidator();
        return $validator->validateValue($this->getContextValue(), $this->getConstraints());
    }

}
