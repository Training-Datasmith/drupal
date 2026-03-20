<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Drupal\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Alias;
use Symfony\Component\Dependency_Injection\Container as SymfonyContainer;
use Symfony\Component\Dependency_Injection\Container_Builder as SymfonyContainerBuilder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag_Interface;
/**
 * Drupal's dependency injection container builder.
 *
 * @todo Submit upstream patches to Symfony to not require these overrides.
 *
 * @ingroup container
 */
class Container_Builder extends Symfony_Container_Builder implements Container_Interface
{
    /**
     * {@inheritdoc}
     */
    public function __construct(?Parameter_Bag_Interface $parameter_bag = null)
    {
        parent::__construct($parameter_bag);
        $this->set_resource_tracking(false);
    }
    /**
     * Overrides Symfony\Component\DependencyInjection\ContainerBuilder::set().
     *
     * Drupal's container builder can be used at runtime after compilation, so we
     * override Symfony's ContainerBuilder's restriction on setting services in a
     * frozen builder.
     *
     * @todo Restrict this to synthetic services only. Ideally, the upstream
     *   ContainerBuilder class should be fixed to allow setting synthetic
     *   services in a frozen builder.
     */
    public function set(string $id, ?object $service): void
    {
        Symfony_Container::set($id, $service);
    }
    /**
     * {@inheritdoc}
     */
    public function register($id, $class = null): Definition
    {
        $definition = new Definition($class);
        // As of Symfony 5.2 all services are private by default, but in Drupal
        // services are still public by default.
        $definition->set_public(true);
        return $this->set_definition($id, $definition);
    }
    /**
     * {@inheritdoc}
     */
    public function set_alias($alias, $id): Alias
    {
        $alias = parent::set_alias($alias, $id);
        // As of Symfony 3.4 all aliases are private by default.
        $alias->set_public(true);
        return $alias;
    }
    /**
     * {@inheritdoc}
     */
    public function set_parameter(string $name, array|bool|string|int|float|\Unit_Enum|null $value): void
    {
        if (strtolower($name) !== $name) {
            throw new \InvalidArgumentException("Parameter names must be lowercase: {$name}");
        }
        parent::set_parameter($name, $value);
    }
    /**
     * {@inheritdoc}
     */
    public function __sleep(): array
    {
        assert(false, 'The container was serialized.');
        return array_keys(get_object_vars($this));
    }
}