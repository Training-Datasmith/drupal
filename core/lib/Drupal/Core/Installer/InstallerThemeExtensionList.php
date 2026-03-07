<?php

declare(strict_types=1);

namespace Drupal\Core\Installer;

use Drupal\Core\Extension\ThemeExtensionList;

/**
 * Overrides the theme extension list to have a static cache.
 */
class InstallerThemeExtensionList extends ThemeExtensionList
{
    use ExtensionListTrait;

}
