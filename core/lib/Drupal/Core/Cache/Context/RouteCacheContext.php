<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the RouteCacheContext service, for "per route" caching.
 *
 * Cache context ID: 'route'.
 */
class Route_Cache_Context implements Cache_Context_Interface
{
    /**
     * Constructs a new RouteCacheContext class.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
     *   The route match.
     */
    public function __construct(protected \Drupal\Core\Routing\Route_Match_Interface $route_match)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Route');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context(): string
    {
        return $this->route_match->get_route_name() . hash('sha256', serialize($this->route_match->get_raw_parameters()->all()));
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}