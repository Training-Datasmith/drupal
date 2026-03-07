<?php

namespace Drupal\Core\Cache;

/**
 * Trait to implement CacheableDependencyInterface for uncacheable objects.
 *
 * Use this for objects that are never cacheable.
 *
 * @see \Drupal\Core\Cache\CacheableDependencyInterface
 */
trait UncacheableDependencyTrait {

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
    return 0;
  }

}
