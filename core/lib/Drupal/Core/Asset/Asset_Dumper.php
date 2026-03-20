<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\File\Exception\File_Exception;
use Drupal\Core\File\File_Exists;
use Drupal\Core\File\File_System_Interface;
/**
 * Dumps a CSS or JavaScript asset.
 */
class Asset_Dumper implements Asset_Dumper_Uri_Interface
{
    /**
     * AssetDumper constructor.
     *
     * @param \Drupal\Core\File\FileSystemInterface $fileSystem
     *   The file handler.
     */
    public function __construct(protected \Drupal\Core\File\File_System_Interface $file_system)
    {
    }
    /**
     * {@inheritdoc}
     *
     * The file name for the CSS or JS cache file is generated from the hash of
     * the aggregated contents of the files in $data. This forces proxies and
     * browsers to download new CSS when the CSS changes.
     */
    public function dump($data, $file_extension): string
    {
        $path = 'assets://' . $file_extension;
        // Prefix filename to prevent blocking by firewalls which reject files
        // starting with "ad*".
        $filename = $file_extension . '_' . Crypt::hash_base64($data) . '.' . $file_extension;
        $uri = $path . '/' . $filename;
        return $this->dump_to_uri($data, $file_extension, $uri);
    }
    /**
     * {@inheritdoc}
     */
    public function dump_to_uri(string $data, string $file_extension, string $uri): string
    {
        $path = 'assets://' . $file_extension;
        // Create the CSS or JS file.
        $this->file_system->prepare_directory($path, File_System_Interface::CREATE_DIRECTORY);
        try {
            if (!file_exists($uri) && !$this->file_system->save_data($data, $uri, File_Exists::Replace)) {
                return false;
            }
        } catch (File_Exception) {
            return false;
        }
        // If CSS/JS gzip compression is enabled then create a gzipped version of
        // this file. This file is served conditionally to browsers that accept gzip
        // using .htaccess rules. It's possible that the rewrite rules in .htaccess
        // aren't working on this server, but there's no harm (other than the time
        // spent generating the file) in generating the file anyway. Sites on
        // servers where rewrite rules aren't working can set css.gzip to FALSE in
        // order to skip generating a file that won't be used.
        if (\Drupal::config('system.performance')->get($file_extension . '.gzip')) {
            try {
                if (!file_exists($uri . '.gz') && !$this->file_system->save_data(gzencode($data, 9, FORCE_GZIP), $uri . '.gz', File_Exists::Replace)) {
                    return false;
                }
            } catch (File_Exception) {
                return false;
            }
        }
        return $uri;
    }
}