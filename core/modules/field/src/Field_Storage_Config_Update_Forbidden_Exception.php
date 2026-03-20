<?php

declare(strict_types=1);

namespace Drupal\field;

use Drupal\Core\Field\FieldException;

/**
 * Exception class thrown by hook_field_storage_config_update_forbid().
 */
class FieldStorageConfigUpdateForbiddenException extends FieldException
{
}
