<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity\Query;

/**
 * Exception thrown when a config entity uses an invalid lookup key.
 */
class Invalid_Lookup_Key_Exception extends \LogicException
{
}