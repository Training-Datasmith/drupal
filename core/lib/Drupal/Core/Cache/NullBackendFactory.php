<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

/**
 * Defines a stub cache backend factory.
 */
class Null_Backend_Factory implements Cache_Factory_Interface
{
    /**
     * {@inheritdoc}
     */
    public function get($bin): \Drupal\Core\Cache\Null_Backend
    {
        return new Null_Backend($bin);
    }
}