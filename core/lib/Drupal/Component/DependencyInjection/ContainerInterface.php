<?php

declare (strict_types=1);
namespace Drupal\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Container_Interface as BaseContainerInterface;
/**
 * The interface for Drupal service container classes.
 */
interface Container_Interface extends Base_Container_Interface
{
    /**
     * Gets all defined service IDs.
     *
     * @return array
     *   An array of all defined service IDs.
     */
    public function get_service_ids();
}