<?php

declare(strict_types=1);

namespace Drupal\Core\File\Exception;

/**
 * Exception thrown when a target is not a regular file (e.g. a directory).
 */
class NotRegularFileException extends FileException
{
}
