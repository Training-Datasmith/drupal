<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\File_Security\File_Security;
use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Drupal\Core\File\File_System_Interface;
use Drupal\Core\Serialization\Yaml;
/**
 * Defines the file storage.
 */
class File_Storage implements Storage_Interface
{
    /**
     * The file cache object.
     *
     * @var \Drupal\Component\FileCache\FileCacheInterface
     */
    protected $file_cache;
    /**
     * Constructs a new FileStorage.
     *
     * @param string $directory
     *   A directory path to use for reading and writing of configuration files.
     * @param string $collection
     *   (optional) The collection to store configuration in. Defaults to the
     *   default collection.
     */
    public function __construct(
        /**
         * The filesystem path for configuration objects.
         */
        protected $directory,
        /**
         * The storage collection.
         */
        protected $collection = Storage_Interface::DEFAULT_COLLECTION
    )
    {
        // Use a NULL File Cache backend by default. This will ensure only the
        // internal static caching of FileCache is used and thus avoids blowing up
        // the APCu cache.
        $this->file_cache = File_Cache_Factory::get('config', ['cache_backend_class' => null]);
    }
    /**
     * Returns the path to the configuration file.
     *
     * @return string
     *   The path to the configuration file.
     */
    public function get_file_path(string $name): string
    {
        return $this->get_collection_directory() . '/' . $name . '.' . static::get_file_extension();
    }
    /**
     * Gets the extension used by the file storage for all configuration files.
     *
     * @return string
     *   The file extension.
     */
    public static function get_file_extension(): string
    {
        return 'yml';
    }
    /**
     * Check if the directory exists and create it if not.
     */
    protected function ensure_storage(): static
    {
        $dir = $this->get_collection_directory();
        $success = $this->get_file_system()->prepare_directory($dir, File_System_Interface::CREATE_DIRECTORY | File_System_Interface::MODIFY_PERMISSIONS);
        // Only create .htaccess file in root directory.
        if ($dir == $this->directory) {
            $success = $success && File_Security::write_htaccess($this->directory);
        }
        if (!$success) {
            throw new Storage_Exception('Failed to create config directory ' . $dir);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name): bool
    {
        return file_exists($this->get_file_path($name));
    }
    /**
     * Implements Drupal\Core\Config\StorageInterface::read().
     *
     * @throws \Drupal\Core\Config\UnsupportedDataTypeConfigException
     */
    public function read($name)
    {
        if (!$this->exists($name)) {
            return false;
        }
        $filepath = $this->get_file_path($name);
        if ($data = $this->file_cache->get($filepath)) {
            return $data;
        }
        $data = file_get_contents($filepath);
        try {
            $data = $this->decode($data);
        } catch (Invalid_Data_Type_Exception $e) {
            throw new Unsupported_Data_Type_Config_Exception('Invalid data type in config ' . $name . ', found in file ' . $filepath . ': ' . $e->get_message());
        }
        $this->file_cache->set($filepath, $data);
        return $data;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function read_multiple(array $names): array
    {
        $list = [];
        foreach ($names as $name) {
            if ($data = $this->read($name)) {
                $list[$name] = $data;
            }
        }
        return $list;
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data): bool
    {
        try {
            $encoded_data = $this->encode($data);
        } catch (Invalid_Data_Type_Exception $e) {
            throw new Storage_Exception("Invalid data type in config {$name}: {$e->get_message()}");
        }
        $target = $this->get_file_path($name);
        $status = @file_put_contents($target, $encoded_data);
        if ($status === false) {
            // Try to make sure the directory exists and try writing again.
            $this->ensure_storage();
            $status = @file_put_contents($target, $encoded_data);
        }
        if ($status === false) {
            throw new Storage_Exception('Failed to write configuration file: ' . $target);
        }
        $this->file_cache->set($target, $data);
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        if (!$this->exists($name)) {
            return false;
        }
        $this->file_cache->delete($this->get_file_path($name));
        return $this->get_file_system()->unlink($this->get_file_path($name));
    }
    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name): bool
    {
        $status = @rename($this->get_file_path($name), $this->get_file_path($new_name));
        if ($status === false) {
            return false;
        }
        $this->file_cache->delete($this->get_file_path($name));
        $this->file_cache->delete($this->get_file_path($new_name));
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return Yaml::encode($data);
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw): false|array
    {
        $data = Yaml::decode($raw);
        // A simple string is valid YAML for any reason.
        if (!is_array($data)) {
            return false;
        }
        return $data;
    }
    /**
     * {@inheritdoc}
     * @return string[]
     */
    public function list_all($prefix = ''): array
    {
        $dir = $this->get_collection_directory();
        if (!is_dir($dir)) {
            return [];
        }
        $extension = '.' . static::get_file_extension();
        // glob() directly calls into libc glob(), which is not aware of PHP stream
        // wrappers. Same for \GlobIterator (which additionally requires an absolute
        // realpath() on Windows).
        // @see https://github.com/mikey179/vfsStream/issues/2
        $files = scandir($dir);
        $names = [];
        $pattern = '/^' . preg_quote($prefix, '/') . '.*' . preg_quote($extension, '/') . '$/';
        foreach ($files as $file) {
            if ($file[0] !== '.' && preg_match($pattern, $file)) {
                $names[] = basename($file, $extension);
            }
        }
        return $names;
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = '')
    {
        $files = $this->list_all($prefix);
        $success = !empty($files);
        foreach ($files as $name) {
            if (!$this->delete($name) && $success) {
                $success = false;
            }
        }
        if ($success && $this->collection != Storage_Interface::DEFAULT_COLLECTION) {
            // Remove empty directories.
            if (!(new \Filesystem_Iterator($this->get_collection_directory()))->valid()) {
                $this->get_file_system()->rmdir($this->get_collection_directory());
            }
        }
        return $success;
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): static
    {
        return new static($this->directory, $collection);
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name()
    {
        return $this->collection;
    }
    /**
     * {@inheritdoc}
     */
    public function get_all_collection_names()
    {
        if (!is_dir($this->directory)) {
            return [];
        }
        $collections = $this->get_all_collection_names_helper($this->directory);
        sort($collections);
        return $collections;
    }
    /**
     * Helper function for getAllCollectionNames().
     *
     * If the file storage has the following subdirectory structure:
     *   ./another_collection/one
     *   ./another_collection/two
     *   ./collection/sub/one
     *   ./collection/sub/two
     * this function will return:
     * @code
     *   [
     *     'another_collection.one',
     *     'another_collection.two',
     *     'collection.sub.one',
     *     'collection.sub.two',
     *   ];
     * @endcode
     *
     * @param string $directory
     *   The directory to check for sub directories. This allows this
     *   function to be used recursively to discover all the collections in the
     *   storage. It is the responsibility of the caller to ensure the directory
     *   exists.
     *
     * @return array
     *   A list of collection names contained within the provided directory.
     */
    protected function get_all_collection_names_helper(string $directory): array
    {
        $collections = [];
        $pattern = '/\.' . preg_quote(static::get_file_extension(), '/') . '$/';
        foreach (new \Directory_Iterator($directory) as $fileinfo) {
            if ($fileinfo->is_dir() && !$fileinfo->is_dot()) {
                $collection = $fileinfo->get_filename();
                // Recursively call getAllCollectionNamesHelper() to discover if there
                // are subdirectories. Subdirectories represent a dotted collection
                // name.
                $sub_collections = $this->get_all_collection_names_helper($directory . '/' . $collection);
                if (!empty($sub_collections)) {
                    // Build up the collection name by concatenating the subdirectory
                    // names with the current directory name.
                    foreach ($sub_collections as $sub_collection) {
                        $collections[] = $collection . '.' . $sub_collection;
                    }
                }
                // Check that the collection is valid by searching it for configuration
                // objects. A directory without any configuration objects is not a valid
                // collection.
                // @see \Drupal\Core\Config\FileStorage::listAll()
                foreach (scandir($directory . '/' . $collection) as $file) {
                    if ($file[0] !== '.' && preg_match($pattern, $file)) {
                        $collections[] = $collection;
                        break;
                    }
                }
            }
        }
        return $collections;
    }
    /**
     * Gets the directory for the collection.
     *
     * @return string
     *   The directory for the collection.
     */
    protected function get_collection_directory()
    {
        if ($this->collection == Storage_Interface::DEFAULT_COLLECTION) {
            return $this->directory;
        }
        return $this->directory . '/' . str_replace('.', '/', $this->collection);
    }
    /**
     * Returns file system service.
     *
     * @return \Drupal\Core\File\FileSystemInterface
     *   The file system service.
     */
    private function get_file_system(): object
    {
        return \Drupal::service('file_system');
    }
}