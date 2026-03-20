<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

/**
 * Finds files that are located in the supported 'libraries' directories.
 */
class Libraries_Directory_File_Finder
{
    /**
     * Constructs a new LibrariesDirectoryFileFinder instance.
     *
     * @param string $root
     *   The app root.
     * @param string $sitePath
     *   The site path.
     * @param \Drupal\Core\Extension\ProfileExtensionList $profileExtensionList
     *   The profile extension list.
     * @param string $installProfile
     *   The install profile.
     */
    public function __construct(
        /**
         * The app root.
         */
        protected $root,
        /**
         * The site path.
         */
        protected $site_path,
        protected \Drupal\Core\Extension\Profile_Extension_List $profile_extension_list,
        /**
         * The install profile.
         */
        protected $install_profile
    )
    {
    }
    /**
     * Finds files that are located in the supported 'libraries' directories.
     *
     * It searches the following locations:
     * - A libraries directory in the current site directory, for example:
     *   sites/default/libraries.
     * - The root libraries directory.
     * - A libraries directory in the selected installation profile, for example:
     *   profiles/my_install_profile/libraries.
     * If the same library is present in multiple locations the first location
     * found will be used. The locations are searched in the order listed.
     *
     * @param string $path
     *   The path for the library file to find.
     *
     * @return string|false
     *   The real path to the library file relative to the root directory. If the
     *   library cannot be found then FALSE.
     */
    public function find(string $path): string|false
    {
        // Search sites/<domain>/*.
        $directories[] = "{$this->site_path}/libraries/";
        // Always search the root 'libraries' directory.
        $directories[] = 'libraries/';
        // Installation profiles can place libraries into a 'libraries' directory.
        if ($this->install_profile) {
            $profile_path = $this->profile_extension_list->get_path($this->install_profile);
            $directories[] = "{$profile_path}/libraries/";
        }
        foreach ($directories as $dir) {
            if (file_exists($this->root . '/' . $dir . $path)) {
                return $dir . $path;
            }
        }
        // The library has not been found.
        return false;
    }
}