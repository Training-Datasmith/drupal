<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
use Drupal\Core\Menu\Menu_Active_Trail_Interface;
/**
 * Defines the MenuActiveTrailsCacheContext service.
 */
class Menu_Active_Trails_Cache_Context implements Calculated_Cache_Context_Interface
{
    /**
     * Constructs a MenuActiveTrailsCacheContext object.
     *
     * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrailService
     *   The menu active trail service.
     */
    public function __construct(protected Menu_Active_Trail_Interface $menu_active_trail_service)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Active menu trail');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context($menu_name = null): string
    {
        if (!$menu_name) {
            throw new \LogicException('No menu name provided for menu.active_trails cache context.');
        }
        $active_trail = $this->menu_active_trail_service->get_active_trail_ids($menu_name);
        return 'menu_trail.' . $menu_name . '|' . implode('|', $active_trail);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata($menu_name = null)
    {
        if (!$menu_name) {
            throw new \LogicException('No menu name provided for menu.active_trails cache context.');
        }
        $cacheable_metadata = new Cacheable_Metadata();
        return $cacheable_metadata->set_cache_tags(["config:system.menu.{$menu_name}"]);
    }
}