<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Site\Settings;
/**
 * Provides a factory for creating config file storage objects.
 */
class File_Storage_Factory
{
    /**
     * Returns a FileStorage object working with the sync config directory.
     *
     * @return \Drupal\Core\Config\FileStorage
     *   The file storage object for the configuration sync directory.
     *
     * @throws \Drupal\Core\Config\ConfigDirectoryNotDefinedException
     *   In case the sync directory does not exist or is not defined in
     *   $settings['config_sync_directory'].
     */
    public static function get_sync(): \Drupal\Core\Config\File_Storage
    {
        $directory = Settings::get('config_sync_directory', false);
        if ($directory === false) {
            throw new Config_Directory_Not_Defined_Exception('The config sync directory is not defined in $settings["config_sync_directory"]');
        }
        return new File_Storage($directory);
    }
}