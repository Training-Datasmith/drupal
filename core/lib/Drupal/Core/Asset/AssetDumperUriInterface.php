<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

/**
 * Interface defining a service that dumps an asset to a specified location.
 */
interface Asset_Dumper_Uri_Interface extends Asset_Dumper_Interface
{
    /**
     * Dumps an (optimized) asset to persistent storage.
     *
     * @param string $data
     *   The asset's contents.
     * @param string $file_extension
     *   The file extension of this asset.
     * @param string $uri
     *   The URI to dump to.
     *
     * @return string
     *   An URI to access the dumped asset.
     */
    public function dump_to_uri(string $data, string $file_extension, string $uri): string;
}