<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

/**
 * Defines an interface for cached configuration storage.
 */
interface StorageCacheInterface
{
    /**
     * Reset the static cache of the listAll() cache.
     */
    public function resetListCache();

}
