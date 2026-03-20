<?php

declare(strict_types=1);

namespace Drupal\file\Validation;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\TypedData\Validation\RecursiveValidator;
use Drupal\Core\Validation\ConstraintValidatorFactory;
use Drupal\Core\Validation\DrupalTranslator;
use Drupal\Core\Validation\ExecutionContextFactory;

/**
 * Factory for creating a new RecursiveValidator.
 */
class RecursiveValidatorFactory
{
    /**
     * Constructs a new RecursiveValidatorFactory.
     *
     * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
     *   The class resolver.
     * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
     *   The typed data manager.
     */
    public function __construct(
        protected ClassResolverInterface $classResolver,
        protected TypedDataManagerInterface $typedDataManager,
    ) {
    }

    /**
     * Creates a new RecursiveValidator.
     *
     * @return \Drupal\Core\TypedData\Validation\RecursiveValidator
     *   The validator.
     */
    public function createValidator(): RecursiveValidator
    {
        return new RecursiveValidator(
            new ExecutionContextFactory(new DrupalTranslator()),
            new ConstraintValidatorFactory($this->classResolver),
            $this->typedDataManager,
        );
    }

}
