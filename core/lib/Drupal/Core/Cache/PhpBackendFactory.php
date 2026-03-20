<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\Time_Interface;
/**
 * Defines a PHP cache backend factory.
 */
class Php_Backend_Factory implements Cache_Factory_Interface
{
    /**
     * Constructs a PhpBackendFactory object.
     *
     * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
     *   The cache tags checksum provider.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(protected \Drupal\Core\Cache\Cache_Tags_Checksum_Interface $checksum_provider, protected Time_Interface $time)
    {
    }
    /**
     * Gets PhpBackend for the specified cache bin.
     *
     * @param string $bin
     *   The cache bin for which the object is created.
     *
     * @return \Drupal\Core\Cache\PhpBackend
     *   The cache backend object for the specified cache bin.
     */
    public function get($bin): \Drupal\Core\Cache\Php_Backend
    {
        return new Php_Backend($bin, $this->checksum_provider, $this->time);
    }
}