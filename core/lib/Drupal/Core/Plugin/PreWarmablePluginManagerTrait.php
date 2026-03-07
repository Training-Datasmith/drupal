<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin;

/**
 * Provides a trait for Drupal\Core\PreWarm\PreWarmableInterface.
 *
 * For the vast majority of plugin managers, the ::getDefinitions() method does
 * exactly the right logic for cache prewarming, so this provides a default
 * implementation that uses that.
 *
 * @phpstan-require-implements \Drupal\Component\Plugin\Discovery\DiscoveryInterface
 * @phpstan-require-implements \Drupal\Core\PreWarm\PreWarmableInterface
 */
trait PreWarmablePluginManagerTrait
{
    /**
     * Implements \Drupal\Core\PreWarm\PreWarmableInterface.
     */
    public function preWarm(): void
    {
        $this->getDefinitions();
    }

}
