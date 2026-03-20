<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Defines a common interface for dependency container injection.
 *
 * This interface gives classes who need services a factory method for
 * instantiation rather than defining a new service.
 */
interface Container_Injection_Interface
{
    /**
     * Instantiates a new instance of this class.
     *
     * This is a factory method that returns a new instance of this class. The
     * factory should pass any needed dependencies into the constructor of this
     * class, but not the container itself. Every call to this method must return
     * a new instance of this class; that is, it may not implement a singleton.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   The service container this instance should use.
     */
    public static function create(Container_Interface $container);
}