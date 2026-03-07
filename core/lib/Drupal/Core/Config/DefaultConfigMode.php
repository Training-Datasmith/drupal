<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

/**
 * Provides modes for ConfigInstallerInterface::installDefaultConfig().
 *
 * @see \Drupal\Core\Config\ConfigInstallerInterface::installDefaultConfig()
 */
enum DefaultConfigMode
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
    public function createInstallConfig(): bool
    {
        return match($this) {
            DefaultConfigMode::All, DefaultConfigMode::InstallSimple, DefaultConfigMode::InstallEntities => true,
            default => false,
        };
    }

    /**
     * Determines if config in /optional directory should be created.
     *
     * @return bool
     *   TRUE to create config in /optional directory, FALSE if not.
     */
    public function createOptionalConfig(): bool
    {
        return match($this) {
            DefaultConfigMode::All, DefaultConfigMode::Optional => true,
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
    public function createSiteOptionalConfig(): bool
    {
        return match($this) {
            DefaultConfigMode::All, DefaultConfigMode::SiteOptional => true,
            default => false,
        };
    }

}
