<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity\Exception;

use Drupal\Core\Config\Config_Exception;
/**
 * Thrown when a storage class is not an instance of ConfigEntityStorage.
 */
class Config_Entity_Storage_Class_Exception extends Config_Exception
{
}