<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

/**
 * Exception, thrown when a method is called on a non-initialized container.
 *
 * @see \Drupal
 */
class Container_Not_Initialized_Exception extends \RuntimeException
{
}