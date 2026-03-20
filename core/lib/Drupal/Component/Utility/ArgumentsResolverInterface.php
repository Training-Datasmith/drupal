<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Resolves the arguments to pass to a callable.
 */
interface Arguments_Resolver_Interface
{
    /**
     * Gets arguments suitable for passing to the given callable.
     *
     * @return array
     *   An array of arguments to pass to the callable.
     *
     * @throws \RuntimeException
     *   When a value for an argument given cannot be resolved.
     */
    public function get_arguments(callable $callable);
}