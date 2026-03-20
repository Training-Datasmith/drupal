<?php

declare (strict_types=1);
namespace Drupal\Component\Discovery;

/**
 * Interface for classes providing a type of discovery.
 */
interface Discoverable_Interface
{
    /**
     * Returns an array of discoverable items.
     *
     * @return array
     *   An array of discovered data keyed by provider.
     *
     * @throws \Drupal\Component\Discovery\DiscoveryException
     *   Exception thrown if there is a problem during discovery.
     */
    public function find_all();
}