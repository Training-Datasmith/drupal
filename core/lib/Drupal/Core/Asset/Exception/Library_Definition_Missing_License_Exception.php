<?php

declare (strict_types=1);
namespace Drupal\Core\Asset\Exception;

/**
 * Defines a custom exception if a library has a remote but no license.
 */
class Library_Definition_Missing_License_Exception extends \RuntimeException
{
}