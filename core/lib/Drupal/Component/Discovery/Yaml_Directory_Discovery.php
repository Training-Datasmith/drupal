<?php

declare (strict_types=1);
namespace Drupal\Component\Discovery;

use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\File_System\Regex_Directory_Iterator;
use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Drupal\Component\Serialization\Yaml;
/**
 * Discovers multiple YAML files in a set of directories.
 */
class Yaml_Directory_Discovery implements Discoverable_Interface
{
    /**
     * Defines the key in the discovered data where the file path is stored.
     */
    public const FILE_KEY = '_discovered_file_path';
    /**
     * Constructs a YamlDirectoryDiscovery object.
     *
     * @param array $directories
     *   An array of directories to scan, keyed by the provider. The value can
     *   either be a string or an array of strings. The string values should be
     *   the path of a directory to scan.
     * @param string $fileCacheKeySuffix
     *   The file cache key suffix. This should be unique for each type of
     *   discovery.
     * @param string $idKey
     *   (optional) The key contained in the discovered data that identifies it.
     *   Defaults to 'id'.
     */
    public function __construct(
        protected array $directories,
        /**
         * The suffix for the file cache key.
         */
        protected $file_cache_key_suffix,
        /**
         * The key contained in the discovered data that identifies it.
         */
        protected $id_key = 'id'
    )
    {
    }
    /**
     * {@inheritdoc}
     * @return non-empty-array[]
     */
    public function find_all(): array
    {
        $all = [];
        $files = $this->find_files();
        $file_cache = File_Cache_Factory::get('yaml_discovery:' . $this->file_cache_key_suffix);
        // Try to load from the file cache first.
        foreach ($file_cache->get_multiple(array_keys($files)) as $file => $data) {
            $all[$files[$file]][$this->get_identifier($file, $data)] = $data;
            unset($files[$file]);
        }
        // If there are files left that were not returned from the cache, load and
        // parse them now. This list was flipped above and is keyed by filename.
        if ($files) {
            foreach ($files as $file => $provider) {
                // If a file is empty or its contents are commented out, return an empty
                // array instead of NULL for type consistency.
                try {
                    $data = Yaml::decode(file_get_contents($file)) ?: [];
                } catch (Invalid_Data_Type_Exception $e) {
                    throw new Discovery_Exception("The {$file} contains invalid YAML", 0, $e);
                }
                $data[static::FILE_KEY] = $file;
                $all[$provider][$this->get_identifier($file, $data)] = $data;
                $file_cache->set($file, $data);
            }
        }
        return $all;
    }
    /**
     * Gets the identifier from the data.
     *
     * @param string $file
     *   The filename.
     * @param array $data
     *   The data from the YAML file.
     *
     * @return string
     *   The identifier from the data.
     */
    protected function get_identifier($file, array $data)
    {
        if (!isset($data[$this->id_key])) {
            throw new Discovery_Exception("The {$file} contains no data in the identifier key '{$this->id_key}'");
        }
        return $data[$this->id_key];
    }
    /**
     * Returns an array of providers keyed by file path.
     *
     * @return array
     *   An array of providers keyed by file path.
     */
    protected function find_files(): array
    {
        $file_list = [];
        foreach ($this->directories as $provider => $directories) {
            $directories = (array) $directories;
            foreach ($directories as $directory) {
                if (is_dir($directory)) {
                    /** @var \SplFileInfo $fileInfo */
                    foreach ($this->get_directory_iterator($directory) as $file_info) {
                        $file_list[$file_info->get_pathname()] = $provider;
                    }
                }
            }
        }
        return $file_list;
    }
    /**
     * Gets an iterator to loop over the files in the provided directory.
     *
     * This method exists so that it is easy to replace this functionality in a
     * class that extends this one. For example, it could be used to make the scan
     * recursive.
     *
     * @param string $directory
     *   The directory to scan.
     *
     * @return \Traversable
     *   An \Traversable object or array where the values are \SplFileInfo
     *   objects.
     */
    protected function get_directory_iterator($directory): \Drupal\Component\File_System\Regex_Directory_Iterator
    {
        return new Regex_Directory_Iterator($directory, '/\.yml$/i');
    }
}