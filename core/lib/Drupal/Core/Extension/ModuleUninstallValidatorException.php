<?php

declare(strict_types=1);

namespace Drupal\Core\Extension;

/**
 * Defines an exception thrown when uninstalling a module that did not validate.
 */
class ModuleUninstallValidatorException extends \InvalidArgumentException
{
}
