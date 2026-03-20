<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Defines an interface for cached configuration storage.
 */
interface Storage_Cache_Interface
{
    /**
     * Reset the static cache of the listAll() cache.
     */
    public function reset_list_cache();
}