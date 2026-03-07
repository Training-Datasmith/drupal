<?php

declare(strict_types=1);

namespace Drupal\Core\Test\Exception;

/**
 * Exception thrown when a test class is missing the 'group' metadata.
 *
 * @see \Drupal\Core\Test\TestDiscovery::getTestClasses()
 */
class MissingGroupException extends \LogicException
{
}
