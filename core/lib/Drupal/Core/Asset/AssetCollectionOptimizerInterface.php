<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

/**
 * Interface defining a service that optimizes a collection of assets.
 */
interface Asset_Collection_Optimizer_Interface
{
    /**
     * Optimizes a collection of assets.
     *
     * @param array $assets
     *   An asset collection.
     * @param array $libraries
     *   An array of library names.
     *
     * @return array
     *   An optimized asset collection.
     */
    public function optimize(array $assets, array $libraries);
    /**
     * Deletes all optimized asset collections assets.
     */
    public function delete_all();
}