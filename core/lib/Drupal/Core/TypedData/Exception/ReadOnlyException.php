<?php

declare(strict_types=1);

namespace Drupal\Core\TypedData\Exception;

/**
 * Exception thrown when trying to write or set ready-only data.
 */
class ReadOnlyException extends \Exception
{
}
