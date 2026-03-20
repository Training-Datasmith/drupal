<?php

declare (strict_types=1);
namespace Drupal\Core\Datetime;

use Drupal\Core\Config\Entity\Config_Entity_Interface;
/**
 * Provides an interface defining a date format.
 */
interface Date_Format_Interface extends Config_Entity_Interface
{
    /**
     * Gets the date pattern string for this format.
     *
     * @return string
     *   The pattern string as expected by date().
     */
    public function get_pattern();
    /**
     * Sets the date pattern for this format.
     *
     * @param string $pattern
     *   The date pattern to use for this format.
     *
     * @return $this
     */
    public function set_pattern($pattern);
    /**
     * Determines if this date format is locked.
     *
     * @return bool
     *   TRUE if the date format is locked, FALSE otherwise.
     */
    public function is_locked();
}