<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Extension\Extension_Discovery;
/**
 * Storage to access configuration and schema in enabled extensions.
 *
 * @see \Drupal\Core\Config\ConfigInstaller
 * @see \Drupal\Core\Config\TypedConfigManager
 */
class Extension_Install_Storage extends Install_Storage
{
    /**
     * Overrides \Drupal\Core\Config\InstallStorage::__construct().
     *
     * @param \Drupal\Core\Config\StorageInterface $configStorage
     *   The active configuration store where the list of enabled modules and
     *   themes is stored.
     * @param string $directory
     *   The directory to scan in each extension to scan for files.
     * @param string $collection
     *   The collection to store configuration in.
     * @param bool $includeProfile
     *   Whether to include the install profile in extensions to
     *   search and to get overrides from.
     * @param string $installProfile
     *   The current installation profile.
     */
    public function __construct(
        protected \Drupal\Core\Config\Storage_Interface $config_storage,
        $directory,
        $collection,
        /**
         * Flag to include the profile in the list of enabled modules.
         */
        protected $include_profile,
        /**
         * The name of the currently active installation profile.
         *
         * In the early installer this value can be NULL.
         */
        protected $install_profile
    )
    {
        parent::__construct($directory, $collection);
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): static
    {
        return new static($this->config_storage, $this->directory, $collection, $this->include_profile, $this->install_profile);
    }
    /**
     * Returns a map of all config object names and their folders.
     *
     * The list is based on enabled modules and themes. The active configuration
     * storage is used rather than \Drupal\Core\Extension\ModuleHandler and
     *  \Drupal\Core\Extension\ThemeHandler in order to resolve circular
     * dependencies between these services and \Drupal\Core\Config\ConfigInstaller
     * and \Drupal\Core\Config\TypedConfigManager.
     *
     * @return array
     *   An array mapping config object names with directories.
     */
    protected function get_all_folders()
    {
        if (!isset($this->folders)) {
            $this->folders = [];
            $this->folders += $this->get_core_names();
            $extensions = $this->config_storage->read('core.extension');
            // @todo Remove this scan as part of https://www.drupal.org/node/2186491
            $listing = new Extension_Discovery(\Drupal::root());
            if (!empty($extensions['module'])) {
                $modules = $extensions['module'];
                // Remove the install profile as this is handled later.
                unset($modules[$this->install_profile]);
                $profile_list = $listing->scan('profile');
                if ($this->install_profile && isset($profile_list[$this->install_profile])) {
                    // Prime the \Drupal\Core\Extension\ExtensionList::getPathname()
                    // static cache with the profile info file location so we can use
                    // ExtensionList::getPath() on the active profile during the module
                    // scan.
                    // @todo Remove as part of https://www.drupal.org/node/2186491
                    /** @var \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list */
                    $profile_extension_list = \Drupal::service('extension.list.profile');
                    $profile_extension_list->set_pathname($this->install_profile, $profile_list[$this->install_profile]->get_pathname());
                }
                $module_list_scan = $listing->scan('module');
                $module_list = [];
                foreach (array_keys($modules) as $module) {
                    if (isset($module_list_scan[$module])) {
                        $module_list[$module] = $module_list_scan[$module];
                    }
                }
                $this->folders += $this->get_component_names($module_list);
            }
            if (!empty($extensions['theme'])) {
                $theme_list_scan = $listing->scan('theme');
                foreach (array_keys($extensions['theme']) as $theme) {
                    if (isset($theme_list_scan[$theme])) {
                        $theme_list[$theme] = $theme_list_scan[$theme];
                    }
                }
                $this->folders += $this->get_component_names($theme_list);
            }
            if ($this->include_profile) {
                // The install profile can override module default configuration. We do
                // this by replacing the config file path from the module/theme with the
                // install profile version if there are any duplicates.
                if ($this->install_profile) {
                    if (!isset($profile_list)) {
                        $profile_list = $listing->scan('profile');
                    }
                    if (isset($profile_list[$this->install_profile])) {
                        $profile_folders = $this->get_component_names([$profile_list[$this->install_profile]]);
                        $this->folders = $profile_folders + $this->folders;
                    }
                }
            }
        }
        return $this->folders;
    }
}