<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action;

/**
 * Exception thrown if there are conflicting shorthand action IDs.
 *
 * @internal
 *   This API is experimental.
 */
class Duplicate_Config_Action_Id_Exception extends \RuntimeException
{
}