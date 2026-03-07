<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Stub implementation of RouteMatchInterface for when there's no matched route.
 */
class NullRouteMatch implements RouteMatchInterface
{
    /**
     * {@inheritdoc}
     */
    public function getRouteName(): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteObject(): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($parameter_name): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return new ParameterBag();
    }

    /**
     * {@inheritdoc}
     */
    public function getRawParameter($parameter_name): null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawParameters()
    {
        return new InputBag();
    }

}
