<?php

declare(strict_types=1);

namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Defines a PHP cache backend factory.
 */
class PhpBackendFactory implements CacheFactoryInterface
{
    /**
     * Constructs a PhpBackendFactory object.
     *
     * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
     *   The cache tags checksum provider.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(protected \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider, protected TimeInterface $time)
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
    public function get($bin): \Drupal\Core\Cache\PhpBackend
    {
        return new PhpBackend($bin, $this->checksumProvider, $this->time);
    }

}
