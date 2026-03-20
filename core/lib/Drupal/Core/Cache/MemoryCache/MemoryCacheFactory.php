<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Memory_Cache;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\Cache\Cache_Factory_Interface;
/**
 * The memory cache factory.
 */
class Memory_Cache_Factory implements Cache_Factory_Interface
{
    /**
     * Instantiated memory cache bins.
     *
     * @var \Drupal\Core\Cache\MemoryBackend[]
     */
    protected $bins = [];
    /**
     * Constructs a MemoryCounterBackendFactory object.
     *
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(protected Time_Interface $time)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get($bin)
    {
        if (!isset($this->bins[$bin])) {
            $this->bins[$bin] = new Memory_Cache($this->time);
        }
        return $this->bins[$bin];
    }
}