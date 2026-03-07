<?php

declare(strict_types=1);

namespace Drupal\Core\Extension\Exception;

/**
 * Exception class thrown when a specified extension has not been installed.
 */
class UninstalledExtensionException extends \InvalidArgumentException
{
}
