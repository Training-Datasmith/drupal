<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Event_Dispatcher\Event;
/**
 * Wraps a configuration event for event listeners.
 */
class Config_Crud_Event extends Event
{
    /**
     * Constructs a configuration event object.
     *
     * @param \Drupal\Core\Config\StorableConfigBase $config
     *   Configuration object.
     */
    public function __construct(
        /**
         * Configuration object.
         */
        protected \Drupal\Core\Config\Storable_Config_Base $config
    )
    {
    }
    /**
     * Gets configuration object.
     *
     * @return \Drupal\Core\Config\StorableConfigBase
     *   The configuration object that caused the event to fire.
     */
    public function get_config()
    {
        return $this->config;
    }
    /**
     * Checks to see if the provided configuration key's value has changed.
     *
     * @param string $key
     *   The configuration key to check if it has changed.
     *
     * @return bool
     *   TRUE if the value of the given key has changed, FALSE otherwise.
     */
    public function is_changed($key): bool
    {
        return $this->config->get($key) !== $this->config->get_original($key);
    }
}