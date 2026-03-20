<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Interface for a storage manager.
 */
interface Storage_Manager_Interface
{
    /**
     * Get the config storage.
     *
     * @return \Drupal\Core\Config\StorageInterface
     *   The config storage.
     *
     * @throws \Drupal\Core\Config\StorageTransformerException
     *   Thrown when the lock could not be acquired.
     */
    public function get_storage();
}