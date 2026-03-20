<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

/**
 * Interface that service providers can implement to modify services.
 *
 * @ingroup container
 */
interface Service_Modifier_Interface
{
    /**
     * Modifies existing service definitions.
     *
     * @param ContainerBuilder $container
     *   The ContainerBuilder whose service definitions can be altered.
     */
    public function alter(Container_Builder $container);
}