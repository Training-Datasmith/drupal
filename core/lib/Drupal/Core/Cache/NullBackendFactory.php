<?php

declare(strict_types=1);

namespace Drupal\Core\Cache;

/**
 * Defines a stub cache backend factory.
 */
class NullBackendFactory implements CacheFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($bin): \Drupal\Core\Cache\NullBackend
    {
        return new NullBackend($bin);
    }

}
