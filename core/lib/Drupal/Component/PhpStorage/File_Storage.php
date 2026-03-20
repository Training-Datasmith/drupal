<?php

declare (strict_types=1);
namespace Drupal\Component\Php_Storage;

use Drupal\Component\File_Security\File_Security;
/**
 * Stores the code as regular PHP files.
 */
class File_Storage implements Php_Storage_Interface
{
    /**
     * The directory where the files should be stored.
     */
    protected string $directory;
    /**
     * Constructs this FileStorage object.
     *
     * @param array $configuration
     *   An associative array, containing at least these two keys:
     *   - directory: The directory where the files should be stored.
     *   - bin: The storage bin. Multiple storage objects can be instantiated with
     *     the same configuration, but for different bins.
     */
    public function __construct(array $configuration)
    {
        $this->directory = $configuration['directory'] . '/' . $configuration['bin'];
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name): bool
    {
        return file_exists($this->get_full_path($name));
    }
    /**
     * {@inheritdoc}
     */
    public function load($name): bool
    {
        // The FALSE returned on failure is enough for the caller to handle this,
        // we do not want a warning too.
        return @(include_once $this->get_full_path($name)) !== false;
    }
    /**
     * {@inheritdoc}
     */
    public function save($name, $code): bool
    {
        $path = $this->get_full_path($name);
        $directory = dirname($path);
        $this->ensure_directory($directory);
        return (bool) file_put_contents($path, $code);
    }
    /**
     * Ensures the directory exists, has the right permissions, and a .htaccess.
     *
     * For compatibility with open_basedir, the requested directory is created
     * using a recursion logic that is based on the relative directory path/tree:
     * It works from the end of the path recursively back towards the root
     * directory, until an existing parent directory is found. From there, the
     * subdirectories are created.
     *
     * @param string $directory
     *   The directory path.
     * @param int $mode
     *   The mode, permissions, the directory should have.
     */
    protected function ensure_directory($directory, $mode = 0777)
    {
        if ($this->create_directory($directory, $mode)) {
            File_Security::write_htaccess($directory);
        }
    }
    /**
     * Ensures the requested directory exists and has the right permissions.
     *
     * For compatibility with open_basedir, the requested directory is created
     * using a recursion logic that is based on the relative directory path/tree:
     * It works from the end of the path recursively back towards the root
     * directory, until an existing parent directory is found. From there, the
     * subdirectories are created.
     *
     * @param string $directory
     *   The directory path.
     * @param int $mode
     *   The mode, permissions, the directory should have.
     *
     * @return bool
     *   TRUE if the directory exists or has been created, FALSE otherwise.
     */
    protected function create_directory($directory, $mode = 0777)
    {
        // If the directory exists already, there's nothing to do.
        if (is_dir($directory)) {
            return true;
        }
        // If the parent directory doesn't exist, try to create it.
        $parent_exists = is_dir($parent = dirname($directory));
        if (!$parent_exists) {
            $parent_exists = $this->create_directory($parent, $mode);
        }
        // If parent exists, try to create the directory and ensure to set its
        // permissions, because mkdir() obeys the umask of the current process.
        if ($parent_exists) {
            // We hide warnings and ignore the return because there may have been a
            // race getting here and the directory could already exist.
            @mkdir($directory);
            // Only try to chmod() if the subdirectory could be created.
            if (is_dir($directory)) {
                // Avoid writing permissions if possible.
                if (fileperms($directory) !== $mode) {
                    return chmod($directory, $mode);
                }
                return true;
            }
            // The directory path is not disclosed to avoid an information
            // disclosure vulnerability. For security reasons, further details are
            // not provided in the error message.
            trigger_error('mkdir(): Permission Denied', E_USER_WARNING);
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $path = $this->get_full_path($name);
        if (file_exists($path)) {
            return $this->unlink($path);
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function get_full_path($name): string
    {
        if (str_contains($name, '..')) {
            throw new \InvalidArgumentException('The name must not contain "..".');
        }
        return $this->directory . '/' . $name;
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all()
    {
        return $this->unlink($this->directory);
    }
    /**
     * Deletes files and/or directories in the specified path.
     *
     * If the specified path is a directory the method will
     * call itself recursively to process the contents. Once the contents have
     * been removed the directory will also be removed.
     *
     * @param string $path
     *   A string containing either a file or directory path.
     *
     * @return bool
     *   TRUE for success or if path does not exist, FALSE in the event of an
     *   error.
     */
    protected function unlink($path)
    {
        if (file_exists($path)) {
            if (is_dir($path)) {
                // Ensure the folder is writable.
                @chmod($path, 0777);
                foreach (new \Directory_Iterator($path) as $fileinfo) {
                    if (!$fileinfo->is_dot()) {
                        $this->unlink($fileinfo->get_path_name());
                    }
                }
                return @rmdir($path);
            }
            // Windows needs the file to be writable.
            @chmod($path, 0700);
            return @unlink($path);
        }
        // If there's nothing to delete return TRUE anyway.
        return true;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function list_all(): array
    {
        $names = [];
        if (file_exists($this->directory)) {
            foreach (new \Directory_Iterator($this->directory) as $fileinfo) {
                if (!$fileinfo->is_dot()) {
                    $name = $fileinfo->get_filename();
                    if ($name != '.htaccess') {
                        $names[] = $name;
                    }
                }
            }
        }
        return $names;
    }
    /**
     * {@inheritdoc}
     */
    public function garbage_collection()
    {
    }
}