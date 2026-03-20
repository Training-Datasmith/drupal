<?php

declare (strict_types=1);
namespace Drupal\Core\Controller;

use Symfony\Component\Http_Kernel\Controller\Controller_Resolver_Interface as BaseControllerResolverInterface;
/**
 * Extends the ControllerResolverInterface from symfony.
 */
interface Controller_Resolver_Interface extends Base_Controller_Resolver_Interface
{
    /**
     * Returns the Controller instance with a given controller route definition.
     *
     * As several resolvers can exist for a single application, a resolver must
     * return false when it is not able to determine the controller.
     *
     * @param mixed $controller
     *   The controller attribute like in
     *   $request->attributes->get(RouteObjectInterface::CONTROLLER_NAME).
     *
     * @return mixed|false
     *   A PHP callable representing the Controller, or false if this resolver is
     *   not able to determine the controller
     *
     * @throws \InvalidArgumentException|\LogicException
     *   Thrown if the controller can't be found.
     *
     * @see \Symfony\Component\HttpKernel\Controller\ControllerResolverInterface::getController()
     */
    public function get_controller_from_definition($controller);
}