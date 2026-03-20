<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

/**
 * Base service provider implementation.
 *
 * @ingroup container
 */
abstract class Service_Provider_Base implements Service_Provider_Interface, Service_Modifier_Interface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container_Builder $container)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function alter(Container_Builder $container)
    {
    }
}