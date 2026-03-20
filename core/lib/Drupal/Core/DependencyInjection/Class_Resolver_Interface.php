<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

/**
 * Provides interface to get an instance of a class with dependency injection.
 */
interface Class_Resolver_Interface
{
    /**
     * Returns a class instance with a given class definition.
     *
     * In contrast to controllers you don't specify a method.
     *
     * @param string $definition
     *   A class name or service name.
     *
     * @return object
     *   The instance of the class.
     *
     * @throws \InvalidArgumentException
     *   If $class is not a valid service identifier and the class does not exist.
     */
    public function get_instance_from_definition($definition);
}