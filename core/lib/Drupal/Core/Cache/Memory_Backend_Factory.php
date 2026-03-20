<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\Time_Interface;
/**
 * Defines a memory cache backend factory.
 */
class Memory_Backend_Factory implements Cache_Factory_Interface
{
    /**
     * Instantiated memory cache bins.
     *
     * @var \Drupal\Core\Cache\MemoryBackend[]
     */
    protected $bins = [];
    /**
     * Constructs a MemoryBackendFactory object.
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
            $this->bins[$bin] = new Memory_Backend($this->time);
        }
        return $this->bins[$bin];
    }
}