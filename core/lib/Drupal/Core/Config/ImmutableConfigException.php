<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Exception throw when an immutable config object is altered.
 *
 * @see \Drupal\Core\Config\ImmutableConfig
 */
class Immutable_Config_Exception extends \LogicException
{
}