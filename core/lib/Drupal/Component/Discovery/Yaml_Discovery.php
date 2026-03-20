<?php

declare (strict_types=1);
namespace Drupal\Component\Discovery;

use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Drupal\Component\Serialization\Yaml;
/**
 * Provides discovery for YAML files within a given set of directories.
 */
class Yaml_Discovery implements Discoverable_Interface
{
    /**
     * Constructs a YamlDiscovery object.
     *
     * @param string $name
     *   The base filename to look for in each directory. The format will be
     *   $provider.$name.yml.
     * @param array $directories
     *   An array of directories to scan, keyed by the provider.
     */
    public function __construct(
        /**
         * The base filename to look for in each directory.
         */
        protected $name,
        protected array $directories
    )
    {
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function find_all(): array
    {
        $all = [];
        $files = $this->find_files();
        $provider_by_files = array_flip($files);
        $file_cache = File_Cache_Factory::get('yaml_discovery:' . $this->name);
        // Try to load from the file cache first.
        foreach ($file_cache->get_multiple($files) as $file => $data) {
            $all[$provider_by_files[$file]] = $data;
            unset($provider_by_files[$file]);
        }
        // If there are files left that were not returned from the cache, load and
        // parse them now. This list was flipped above and is keyed by filename.
        foreach ($provider_by_files as $file => $provider) {
            // If a file is empty or its contents are commented out, return an empty
            // array instead of NULL for type consistency.
            $all[$provider] = $this->decode($file);
            $file_cache->set($file, $all[$provider]);
        }
        return $all;
    }
    /**
     * Decode a YAML file.
     *
     * @param string $file
     *   Yaml file path.
     *
     * @return array
     *   The decoded contents of the YAML file.
     */
    protected function decode(string $file)
    {
        try {
            return Yaml::decode(file_get_contents($file)) ?: [];
        } catch (Invalid_Data_Type_Exception $e) {
            throw new Invalid_Data_Type_Exception($file . ': ' . $e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Returns an array of file paths, keyed by provider.
     *
     * @return array
     *   An array of file paths.
     */
    protected function find_files(): array
    {
        $files = [];
        foreach ($this->directories as $provider => $directory) {
            $file = $directory . '/' . $provider . '.' . $this->name . '.yml';
            if (file_exists($file)) {
                $files[$provider] = $file;
            }
        }
        return $files;
    }
}