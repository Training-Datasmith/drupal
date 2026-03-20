<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Provides modes for ConfigInstallerInterface::installDefaultConfig().
 *
 * @see \Drupal\Core\Config\ConfigInstallerInterface::installDefaultConfig()
 */
enum Default_Config_Mode
{
    case All;
    case InstallSimple;
    case InstallEntities;
    case Optional;
    case SiteOptional;
    /**
     * Determines if config in /install directory should be created.
     *
     * @return bool
     *   TRUE to create config in /install directory, FALSE if not.
     */
    public function create_install_config(): bool
    {
        return match ($this) {
            Default_Config_Mode::All, Default_Config_Mode::InstallSimple, Default_Config_Mode::InstallEntities => true,
            default => false,
        };
    }
    /**
     * Determines if config in /optional directory should be created.
     *
     * @return bool
     *   TRUE to create config in /optional directory, FALSE if not.
     */
    public function create_optional_config(): bool
    {
        return match ($this) {
            Default_Config_Mode::All, Default_Config_Mode::Optional => true,
            default => false,
        };
    }
    /**
     * Determines if optional config in other installed modules should be created.
     *
     * @return bool
     *   TRUE to create optional config in other installed modules,
     *   FALSE if not.
     */
    public function create_site_optional_config(): bool
    {
        return match ($this) {
            Default_Config_Mode::All, Default_Config_Mode::SiteOptional => true,
            default => false,
        };
    }
}