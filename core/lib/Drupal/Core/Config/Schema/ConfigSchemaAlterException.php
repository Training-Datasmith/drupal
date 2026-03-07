<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Schema;

/**
 * Exception for when hook_config_schema_info_alter() adds or removes schema.
 */
class ConfigSchemaAlterException extends \RuntimeException
{
}
