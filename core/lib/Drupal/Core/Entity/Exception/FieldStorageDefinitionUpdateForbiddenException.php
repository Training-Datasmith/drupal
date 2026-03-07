<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Exception;

use Drupal\Core\Entity\EntityStorageException;

/**
 * Exception thrown when a storage definition update is forbidden.
 */
class FieldStorageDefinitionUpdateForbiddenException extends EntityStorageException
{
}
