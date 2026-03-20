<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Component\Utility\Arguments_Resolver;
use Drupal\Core\Routing\Route_Match_Interface;
use Drupal\Core\Session\Account_Interface;
use Symfony\Component\Http_Foundation\Request;
/**
 * Resolves the arguments to pass to an access check callable.
 */
class Access_Arguments_Resolver_Factory implements Access_Arguments_Resolver_Factory_Interface
{
    /**
     * {@inheritdoc}
     */
    public function get_arguments_resolver(Route_Match_Interface $route_match, Account_Interface $account, ?Request $request = null): \Drupal\Component\Utility\Arguments_Resolver
    {
        $route = $route_match->get_route_object();
        // Defaults for the parameters defined on the route object need to be added
        // to the raw arguments.
        $raw_route_arguments = $route_match->get_raw_parameters()->all() + $route->get_defaults();
        $upcasted_route_arguments = $route_match->get_parameters()->all();
        // Parameters which are not defined on the route object, but still are
        // essential for access checking are passed as wildcards to the argument
        // resolver. An access-check method with a parameter of type Route,
        // RouteMatchInterface, AccountInterface or Request will receive those
        // arguments regardless of the parameter name.
        $wildcard_arguments = [$route, $route_match, $account];
        if (isset($request)) {
            $wildcard_arguments[] = $request;
        }
        return new Arguments_Resolver($raw_route_arguments, $upcasted_route_arguments, $wildcard_arguments);
    }
}