<?php

declare (strict_types=1);
namespace Drupal\Core\Controller\Argument_Resolver;

use Drupal\Core\Routing\Route_Match;
use Drupal\Core\Routing\Route_Match_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
/**
 * Yields a RouteMatch object based on the request object passed along.
 */
final class Route_Match_Value_Resolver implements Value_Resolver_Interface
{
    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        return $argument->get_type() === Route_Match_Interface::class || is_subclass_of($argument->get_type(), Route_Match_Interface::class) ? [Route_Match::create_from_request($request)] : [];
    }
}