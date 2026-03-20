<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Event;

use Drupal\Component\Event_Dispatcher\Event;
/**
 * Represents a database event.
 */
abstract class Database_Event extends Event
{
    /**
     * The time of the event.
     */
    public readonly float $time;
    /**
     * Constructs a DatabaseEvent object.
     */
    public function __construct()
    {
        $this->time = microtime(true);
    }
}