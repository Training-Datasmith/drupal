<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines the ThemeCacheContext service, for "per theme" caching.
 *
 * Cache context ID: 'theme'.
 */
class Theme_Cache_Context implements Cache_Context_Interface
{
    /**
     * Constructs a new ThemeCacheContext service.
     *
     * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
     *   The theme manager.
     */
    public function __construct(protected \Drupal\Core\Theme\Theme_Manager_Interface $theme_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Theme');
    }
    /**
     * {@inheritdoc}
     */
    public function get_context()
    {
        return $this->theme_manager->get_active_theme()->get_name() ?: 'stark';
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata(): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}