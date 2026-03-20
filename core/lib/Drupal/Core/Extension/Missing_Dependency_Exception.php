<?php

declare(strict_types=1);

namespace Drupal\Core\Extension;

/**
 * Exception class to throw when modules are missing on install.
 *
 * @see \Drupal\Core\Extension\ModuleInstaller::install()
 */
class MissingDependencyException extends \Exception
{
}
