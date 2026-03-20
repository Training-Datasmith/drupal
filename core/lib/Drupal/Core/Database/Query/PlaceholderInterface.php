<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Query;

/**
 * Interface for a query that accepts placeholders.
 */
interface Placeholder_Interface
{
    /**
     * Returns a unique identifier for this object.
     */
    public function unique_identifier();
    /**
     * Returns the next placeholder ID for the query.
     *
     * @return int
     *   The next available placeholder ID as an integer.
     */
    public function next_placeholder();
}