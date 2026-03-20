<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

/**
 * Interface that all service providers must implement.
 *
 * @ingroup container
 */
interface Service_Provider_Interface
{
    /**
     * Registers services to the container.
     *
     * @param ContainerBuilder $container
     *   The ContainerBuilder to register services to.
     */
    public function register(Container_Builder $container);
}