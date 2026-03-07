<?php

namespace Drupal\Core\Cache;

/**
 * Trait to implement CacheableDependencyInterface for unchanging objects.
 *
 * @see \Drupal\Core\Cache\CacheableDependencyInterface
 */
trait UnchangingCacheableDependencyTrait {

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

}
