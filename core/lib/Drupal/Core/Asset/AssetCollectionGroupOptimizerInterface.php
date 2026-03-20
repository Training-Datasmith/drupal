<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

/**
 * Interface defining a service that optimizes a collection of assets.
 *
 * Contains an additional method to allow for optimizing an asset group.
 */
interface Asset_Collection_Group_Optimizer_Interface extends Asset_Collection_Optimizer_Interface
{
    /**
     * Optimizes a specific group of assets.
     *
     * @param array $group
     *   An asset group.
     *
     * @return string
     *   The optimized string for the group.
     */
    public function optimize_group(array $group): string;
}