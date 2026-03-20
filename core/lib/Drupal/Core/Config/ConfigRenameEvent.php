<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Configuration event fired when renaming a configuration object.
 */
class Config_Rename_Event extends Config_Crud_Event
{
    /**
     * Constructs the config rename event.
     *
     * @param \Drupal\Core\Config\StorableConfigBase $config
     *   The configuration that has been renamed.
     * @param string $oldName
     *   The old configuration object name.
     */
    public function __construct(
        Storable_Config_Base $config,
        /**
         * The old configuration object name.
         */
        protected $old_name
    )
    {
        $this->config = $config;
    }
    /**
     * Gets the old configuration object name.
     *
     * @return string
     *   The old configuration object name.
     */
    public function get_old_name()
    {
        return $this->old_name;
    }
}