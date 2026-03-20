<?php

declare (strict_types=1);
namespace Drupal\Component\Class_Finder;

/**
 * Finds a class in a PSR-0 structure.
 */
interface Class_Finder_Interface
{
    /**
     * Finds a class.
     *
     * @param string $class
     *   The name of the class.
     *
     * @return string|null
     *   The name of the class or NULL if not found.
     */
    public function find_file($class);
}