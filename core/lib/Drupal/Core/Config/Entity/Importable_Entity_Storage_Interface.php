<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Config\Config;
/**
 * Provides an interface for responding to configuration imports.
 *
 * When configuration is synchronized between storages, the entity storage must
 * handle the synchronization of configuration data for its entity.
 */
interface Importable_Entity_Storage_Interface
{
    /**
     * Creates entities upon synchronizing configuration changes.
     *
     * @param string $name
     *   The name of the configuration object.
     * @param \Drupal\Core\Config\Config $new_config
     *   A configuration object containing the new configuration data.
     * @param \Drupal\Core\Config\Config $old_config
     *   A configuration object containing the old configuration data.
     */
    public function import_create($name, Config $new_config, Config $old_config);
    /**
     * Updates entities upon synchronizing configuration changes.
     *
     * @param string $name
     *   The name of the configuration object.
     * @param \Drupal\Core\Config\Config $new_config
     *   A configuration object containing the new configuration data.
     * @param \Drupal\Core\Config\Config $old_config
     *   A configuration object containing the old configuration data.
     *
     * @throws \Drupal\Core\Config\ConfigImporterException
     *   Thrown when the config entity that should be updated can not be found.
     */
    public function import_update($name, Config $new_config, Config $old_config);
    /**
     * Delete entities upon synchronizing configuration changes.
     *
     * @param string $name
     *   The name of the configuration object.
     * @param \Drupal\Core\Config\Config $new_config
     *   A configuration object containing the new configuration data.
     * @param \Drupal\Core\Config\Config $old_config
     *   A configuration object containing the old configuration data.
     */
    public function import_delete($name, Config $new_config, Config $old_config);
    /**
     * Renames entities upon synchronizing configuration changes.
     *
     * @param string $old_name
     *   The original name of the configuration object.
     * @param \Drupal\Core\Config\Config $new_config
     *   A configuration object containing the new configuration data.
     * @param \Drupal\Core\Config\Config $old_config
     *   A configuration object containing the old configuration data.
     */
    public function import_rename($old_name, Config $new_config, Config $old_config);
}