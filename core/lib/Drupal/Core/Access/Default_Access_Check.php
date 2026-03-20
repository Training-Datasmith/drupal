<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Routing\Access\Access_Interface as RoutingAccessInterface;
use Symfony\Component\Routing\Route;
/**
 * Allows access to routes to be controlled by an '_access' boolean parameter.
 */
class Default_Access_Check implements Routing_Access_Interface
{
    /**
     * Checks access to the route based on the _access parameter.
     *
     * @param \Symfony\Component\Routing\Route $route
     *   The route to check against.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    public function access(Route $route)
    {
        if ($route->get_requirement('_access') === 'TRUE') {
            return Access_Result::allowed();
        }
        if ($route->get_requirement('_access') === 'FALSE') {
            return Access_Result::forbidden();
        }
        return Access_Result::neutral();
    }
}