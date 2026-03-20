<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Routing\Access\Access_Interface as RoutingAccessInterface;
use Drupal\Core\Routing\Route_Match_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\Utility\Callable_Resolver;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Routing\Route;
/**
 * Defines an access checker that allows specifying a custom method for access.
 *
 * You should only use it when you are sure that the access callback will not be
 * reused. Good examples in core are Edit or Toolbar module.
 *
 * The method is called on another instance of the controller class, so you
 * cannot reuse any stored property of your actual controller instance used
 * to generate the output.
 */
class Custom_Access_Check implements Routing_Access_Interface
{
    /**
     * Constructs a CustomAccessCheck instance.
     *
     * @param \Drupal\Core\Utility\CallableResolver $callableResolver
     *   The callable resolver.
     * @param \Drupal\Core\Access\AccessArgumentsResolverFactoryInterface $argumentsResolverFactory
     *   The arguments resolver factory.
     */
    public function __construct(protected Callable_Resolver $callable_resolver, protected Access_Arguments_Resolver_Factory_Interface $arguments_resolver_factory)
    {
    }
    /**
     * Checks access for the account and route using the custom access checker.
     *
     * @param \Symfony\Component\Routing\Route $route
     *   The route.
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match object to be checked.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The account being checked.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   Optional, a request. Only supply this parameter when checking the
     *   incoming request.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    public function access(Route $route, Route_Match_Interface $route_match, Account_Interface $account, ?Request $request = null): mixed
    {
        try {
            $callable = $this->callable_resolver->get_callable_from_definition($route->get_requirement('_custom_access'));
        } catch (\InvalidArgumentException) {
            // The custom access controller method was not found.
            throw new \BadMethodCallException(sprintf('The "%s" method is not callable as a _custom_access callback in route "%s"', $route->get_requirement('_custom_access'), $route->get_path()));
        }
        $arguments_resolver = $this->arguments_resolver_factory->get_arguments_resolver($route_match, $account, $request);
        $arguments = $arguments_resolver->get_arguments($callable);
        return call_user_func_array($callable, $arguments);
    }
}