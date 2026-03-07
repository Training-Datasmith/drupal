<?php

declare(strict_types=1);

namespace Drupal\Core\Installer;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Overrides the module extension list to have a static cache.
 */
class InstallerModuleExtensionList extends ModuleExtensionList
{
    use ExtensionListTrait;

}
