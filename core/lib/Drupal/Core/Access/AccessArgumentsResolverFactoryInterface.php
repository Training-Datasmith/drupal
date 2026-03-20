<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Routing\Route_Match_Interface;
use Drupal\Core\Session\Account_Interface;
use Symfony\Component\Http_Foundation\Request;
/**
 * Constructs the arguments resolver instance to use when running access checks.
 */
interface Access_Arguments_Resolver_Factory_Interface
{
    /**
     * Returns the arguments resolver to use when running access checks.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match object to be checked.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The account being checked.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   Optional, the request object.
     *
     * @return \Drupal\Component\Utility\ArgumentsResolverInterface
     *   The parametrized arguments resolver instance.
     */
    public function get_arguments_resolver(Route_Match_Interface $route_match, Account_Interface $account, ?Request $request = null);
}