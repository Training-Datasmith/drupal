<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\Autowiring_Failed_Exception;
use Symfony\Contracts\Service\Attribute\Required;
/**
 * Defines a base trait for automatically wiring dependency arguments.
 */
trait Autowired_Instance_Trait
{
    /**
     * Instantiates a new instance of the implementing class using autowiring.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The service container this instance should use.
     * @param mixed ...$args
     *   Any predefined arguments to pass to the constructor.
     */
    public static function create_instance_autowired(Container_Interface $container, mixed ...$args): static
    {
        $reflection = new \ReflectionClass(static::class);
        if (method_exists(static::class, '__construct')) {
            $parameters = array_slice($reflection->get_method('__construct')->get_parameters(), count($args));
            $args = array_merge($args, self::get_autowire_arguments($container, $parameters, '__construct'));
        }
        $instance = new static(...$args);
        foreach ($reflection->get_methods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!empty($method->get_attributes(Required::class))) {
                $method->invoke($instance, ...self::get_autowire_arguments($container, $method->get_parameters(), $method->get_name()));
            }
        }
        return $instance;
    }
    /**
     * Resolves arguments for a method using autowiring.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The service container.
     * @param \ReflectionParameter[] $parameters
     *   The parameters to resolve.
     * @param string $method_name
     *   The name of the method being called.
     *
     * @return array
     *   The resolved arguments.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
     *   When a service cannot be resolved.
     */
    private static function get_autowire_arguments(Container_Interface $container, array $parameters, string $method_name): array
    {
        $args = [];
        foreach ($parameters as $parameter) {
            $service = ltrim((string) $parameter->get_type(), '?');
            foreach ($parameter->get_attributes(Autowire::class) as $attribute) {
                $service = (string) $attribute->new_instance()->value;
            }
            if ($container->has($service)) {
                $args[] = $container->get($service);
                continue;
            }
            if ($parameter->allows_null()) {
                $args[] = null;
                continue;
            }
            throw new Autowiring_Failed_Exception($service, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s::%s()". Check that either the argument type is correct or the Autowire attribute is passed a valid identifier. Otherwise configure its value explicitly if possible.', $service, $parameter->get_name(), static::class, $method_name));
        }
        return $args;
    }
}