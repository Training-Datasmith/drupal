<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Defines a trait for automatically wiring dependencies from the container.
 *
 * This trait uses reflection and may cause performance issues with classes
 * that will be instantiated multiple times.
 */
trait Autowire_Trait
{
    use Autowired_Instance_Trait;
    /**
     * Instantiates a new instance of the implementing class using autowiring.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The service container this instance should use.
     */
    public static function create(Container_Interface $container): static
    {
        return static::create_instance_autowired($container);
    }
}