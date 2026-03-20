<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Implements the class resolver interface supporting class names and services.
 */
class Class_Resolver implements Class_Resolver_Interface
{
    use Dependency_Serialization_Trait;
    /**
     * Constructs a new ClassResolver object.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The service container.
     */
    public function __construct(protected Container_Interface $container)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_instance_from_definition($definition)
    {
        if ($this->container->has($definition)) {
            $instance = $this->container->get($definition);
        } else {
            if (!class_exists($definition)) {
                throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $definition));
            }
            if (is_subclass_of($definition, \Drupal\Core\Dependency_Injection\Container_Injection_Interface::class)) {
                $instance = $definition::create($this->container);
            } else {
                $instance = new $definition();
            }
        }
        return $instance;
    }
}