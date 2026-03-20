<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Exception thrown when a config object UUID causes a conflict.
 */
class Config_Duplicate_Uuid_Exception extends Config_Exception
{
}