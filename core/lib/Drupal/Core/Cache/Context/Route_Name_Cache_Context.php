<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

/**
 * Defines the RouteCacheContext service, for "per route name" caching.
 *
 * Cache context ID: 'route.name'.
 */
class Route_Name_Cache_Context extends Route_Cache_Context
{
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Route name');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context()
    {
        return $this->route_match->get_route_name();
    }
}