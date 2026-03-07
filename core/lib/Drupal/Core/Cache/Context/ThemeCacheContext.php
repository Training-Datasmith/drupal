<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Defines the ThemeCacheContext service, for "per theme" caching.
 *
 * Cache context ID: 'theme'.
 */
class ThemeCacheContext implements CacheContextInterface {

  /**
   * Constructs a new ThemeCacheContext service.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   */
  public function __construct(protected \Drupal\Core\Theme\ThemeManagerInterface $themeManager)
  {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Theme');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->themeManager->getActiveTheme()->getName() ?: 'stark';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): \Drupal\Core\Cache\CacheableMetadata {
    return new CacheableMetadata();
  }

}
