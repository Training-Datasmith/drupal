<?php

declare (strict_types=1);
namespace Drupal\Core\Controller;

use Drupal\Core\Routing\Route_Object_Interface;
use Drupal\Core\Utility\Callable_Resolver;
use Symfony\Component\Http_Foundation\Request;
/**
 * ControllerResolver to enhance controllers beyond Symfony's basic handling.
 *
 * It adds one behavior:
 *
 *  - By default, a controller name follows the class::method notation. This
 *    class adds the possibility to use a service from the container as a
 *    controller by using a service:method notation (Symfony uses the same
 *    convention).
 */
class Controller_Resolver implements Controller_Resolver_Interface
{
    /**
     * Constructs a new ControllerResolver.
     *
     * @param \Drupal\Core\Utility\CallableResolver $callableResolver
     *   The callable resolver.
     */
    public function __construct(protected Callable_Resolver $callable_resolver)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_controller_from_definition($controller, $path = '')
    {
        try {
            $callable = $this->callable_resolver->get_callable_from_definition($controller);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('The controller for URI "%s" is not callable.', $path), 0, $e);
        }
        return $callable;
    }
    /**
     * {@inheritdoc}
     */
    public function get_controller(Request $request): callable|false
    {
        if (!$controller = $request->attributes->get(Route_Object_Interface::CONTROLLER_NAME)) {
            return false;
        }
        return $this->get_controller_from_definition($controller, $request->get_path_info());
    }
}