<?php

declare(strict_types=1);

namespace Drupal\Core\Extension\Exception;

/**
 * Exception class thrown when a specified extension is not on the filesystem.
 */
class UnknownExtensionException extends \InvalidArgumentException
{
}
