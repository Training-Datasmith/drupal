<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\Time_Interface;
/**
 * Defines the memory counter backend factory.
 */
class Memory_Counter_Backend_Factory implements Cache_Factory_Interface
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
            $this->bins[$bin] = new Memory_Counter_Backend($this->time);
        }
        return $this->bins[$bin];
    }
}