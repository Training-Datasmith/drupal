<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Event_Dispatcher\Event;
/**
 * Class StorageTransformEvent.
 *
 * This event allows subscribers to alter the configuration of the storage that
 * is being transformed.
 */
class Storage_Transform_Event extends Event
{
    /**
     * StorageTransformEvent constructor.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The storage with the configuration to transform.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Interface $storage)
    {
    }
    /**
     * Returns the mutable storage ready to be read from and written to.
     *
     * @return \Drupal\Core\Config\StorageInterface
     *   The config storage.
     */
    public function get_storage()
    {
        return $this->storage;
    }
}