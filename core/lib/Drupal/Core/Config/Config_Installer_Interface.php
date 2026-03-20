<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Interface for classes that install config.
 */
interface Config_Installer_Interface
{
    /**
     * Installs the default configuration of a given extension.
     *
     * When an extension is installed, it searches all the default configuration
     * directories for all other extensions to locate any configuration with its
     * name prefix. For example, the Node module provides the frontpage view as a
     * default configuration file:
     * core/modules/node/config/optional/views.view.frontpage.yml
     * When the Views module is installed after the Node module is already
     * enabled, the frontpage view will be installed.
     *
     * Additionally, the default configuration directory for the extension being
     * installed is searched to discover if it contains default configuration
     * that is owned by other enabled extensions. So, the frontpage view will also
     * be installed when the Node module is installed after Views.
     *
     * @param string $type
     *   The extension type; e.g., 'module' or 'theme'.
     * @param string $name
     *   The name of the module or theme to install default configuration for.
     * @param \Drupal\Core\Config\DefaultConfigMode $mode
     *   The default value DefaultConfigMode::All means create install, optional
     *   and site optional configuration. The other modes create a single type
     *   config.
     *
     * @see \Drupal\Core\Config\ExtensionInstallStorage
     */
    public function install_default_config($type, $name, Default_Config_Mode $mode = Default_Config_Mode::All);
    /**
     * Installs optional configuration.
     *
     * Optional configuration is only installed if:
     * - the configuration does not exist already.
     * - it's a configuration entity.
     * - its dependencies can be met.
     *
     * @param \Drupal\Core\Config\StorageInterface|null $storage
     *   (optional) The configuration storage to search for optional
     *   configuration. If not provided, all enabled extension's optional
     *   configuration directories including the install profile's will be
     *   searched.
     * @param array $dependency
     *   (optional) If set, ensures that the configuration being installed has
     *   this dependency. The format is dependency type as the key ('module',
     *   'theme', or 'config') and the dependency name as the value
     *   ('node', 'olivero', 'views.view.frontpage').
     */
    public function install_optional_config(?Storage_Interface $storage = null, $dependency = []);
    /**
     * Installs all default configuration in the specified collection.
     *
     * The function is useful if the site needs to respond to an event that has
     * just created another collection and we need to check all the installed
     * extensions for any matching configuration. For example, if a language has
     * just been created.
     *
     * @param string $collection
     *   The configuration collection.
     */
    public function install_collection_default_config($collection);
    /**
     * Sets the configuration storage that provides the default configuration.
     *
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The storage.
     *
     * @return $this
     */
    public function set_source_storage(Storage_Interface $storage);
    /**
     * Gets the configuration storage that provides the default configuration.
     *
     * @return \Drupal\Core\Config\StorageInterface|null
     *   The configuration storage that provides the default configuration.
     *   Returns null if the source storage has not been set.
     */
    public function get_source_storage();
    /**
     * Sets the status of the isSyncing flag.
     *
     * @param bool $status
     *   The status of the sync flag.
     *
     * @return $this
     */
    public function set_syncing($status);
    /**
     * Gets the syncing state.
     *
     * @return bool
     *   Returns TRUE is syncing flag set.
     */
    public function is_syncing();
    /**
     * Checks the configuration that will be installed for an extension.
     *
     * @param string $type
     *   Type of extension to install.
     * @param string|array $name
     *   Name or names of extensions to install.
     *
     * @throws \Drupal\Core\Config\UnmetDependenciesException
     * @throws \Drupal\Core\Config\PreExistingConfigException
     */
    public function check_configuration_to_install($type, $name);
}