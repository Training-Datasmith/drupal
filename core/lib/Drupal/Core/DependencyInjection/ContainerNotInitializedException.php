<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

/**
 * Exception, thrown when a method is called on a non-initialized container.
 *
 * @see \Drupal
 */
class ContainerNotInitializedException extends \RuntimeException
{
}
