<?php

declare (strict_types=1);
namespace Drupal\Component\Php_Storage;

use Drupal\Component\Utility\Crypt;
/**
 * Stores PHP code in files with securely hashed names.
 *
 * The goal of this class is to ensure that if a PHP file is replaced with
 * an untrusted one, it does not get loaded. Since mtime granularity is 1
 * second, we cannot prevent an attack that happens within one second of the
 * initial save(). However, it is very unlikely for an attacker exploiting an
 * upload or file write vulnerability to also know when a legitimate file is
 * being saved, discover its hash, undo its file permissions, and override the
 * file with an upload all within a single second. Being able to accomplish
 * that would indicate a site very likely vulnerable to many other attack
 * vectors.
 *
 * Each file is stored in its own unique containing directory. The hash is based
 * on the virtual file name, the containing directory's mtime, and a
 * cryptographically hard to guess secret string. Thus, even if the hashed file
 * name is discovered and replaced by an untrusted file (e.g., via a
 * move_uploaded_file() invocation by a script that performs insufficient
 * validation), the directory's mtime gets updated in the process, invalidating
 * the hash and preventing the untrusted file from getting loaded.
 *
 * This class does not protect against overwriting a file in-place (e.g. a
 * malicious module that does a file_put_contents()) since this will not change
 * the mtime of the directory. MTimeProtectedFileStorage protects against this
 * at the cost of an additional system call for every load() and exists().
 *
 * The containing directory is created with the same name as the virtual file
 * name (slashes removed) to assist with debugging, since the file itself is
 * stored with a name that's meaningless to humans.
 */
class M_Time_Protected_Fast_File_Storage extends File_Storage
{
    /**
     * The secret used in the HMAC.
     *
     * @var string
     */
    protected $secret;
    /**
     * Constructs this MTimeProtectedFastFileStorage object.
     *
     * @param array $configuration
     *   An associated array, containing at least these keys (the rest are
     *   ignored):
     *   - directory: The directory where the files should be stored.
     *   - secret: A cryptographically hard to guess secret string.
     *   -bin. The storage bin. Multiple storage objects can be instantiated with
     *   the same configuration, but for different bins.
     */
    public function __construct(array $configuration)
    {
        parent::__construct($configuration);
        $this->secret = $configuration['secret'];
    }
    /**
     * {@inheritdoc}
     */
    public function save($name, $data)
    {
        $this->ensure_directory($this->directory);
        // Write the file out to a temporary location. Prepend with a '.' to keep it
        // hidden from listings and web servers.
        $temporary_path = $this->tempnam($this->directory, '.');
        if (!$temporary_path || !@file_put_contents($temporary_path, $data)) {
            return false;
        }
        // The file will not be chmod() in the future so this is the final
        // permission.
        chmod($temporary_path, 0444);
        // Determine the exact modification time of the file.
        $mtime = $this->get_uncached_m_time($temporary_path);
        // Move the temporary file into the proper directory. Note that POSIX
        // compliant systems as well as modern Windows perform the rename operation
        // atomically, i.e. there is no point at which another process attempting to
        // access the new path will find it missing.
        $directory = $this->get_containing_directory_full_path($name);
        $this->ensure_directory($directory);
        $full_path = $this->get_full_path($name, $directory, $mtime);
        $result = rename($temporary_path, $full_path);
        // Finally reset the modification time of the directory to match the one of
        // the newly created file. In order to prevent the creation of a file if the
        // directory does not exist, ensure that the path terminates with a
        // directory separator.
        //
        // Recall that when subsequently loading the file, the hash is calculated
        // based on the file name, the containing mtime, and a the secret string.
        // Hence updating the mtime here is comparable to pointing a symbolic link
        // at a new target, i.e., the newly created file.
        if ($result) {
            $result &= touch($directory . '/', $mtime);
        }
        return (bool) $result;
    }
    /**
     * Gets the full path where the file is or should be stored.
     *
     * This function creates a file path that includes a unique containing
     * directory for the file and a file name that is a hash of the virtual file
     * name, a cryptographic secret, and the containing directory mtime. If the
     * file is overridden by an insecure upload script, the directory mtime gets
     * modified, invalidating the file, thus protecting against untrusted code
     * getting executed.
     *
     * @param string $name
     *   The virtual file name. Can be a relative path.
     * @param string $directory
     *   (optional) The directory containing the file. If not passed, this is
     *   retrieved by calling getContainingDirectoryFullPath().
     * @param int $directory_mtime
     *   (optional) The mtime of $directory. Can be passed to avoid an extra
     *   filesystem call when the mtime of the directory is already known.
     *
     * @return string
     *   The full path where the file is or should be stored.
     */
    public function get_full_path($name, &$directory = null, &$directory_mtime = null): string
    {
        if (!isset($directory)) {
            $directory = $this->get_containing_directory_full_path($name);
        }
        if (!isset($directory_mtime)) {
            $directory_mtime = file_exists($directory) ? filemtime($directory) : 0;
        }
        return $directory . '/' . Crypt::hmac_base64($name, $this->secret . $directory_mtime) . '.php';
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $path = $this->get_containing_directory_full_path($name);
        if (file_exists($path)) {
            return $this->unlink($path);
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function garbage_collection(): void
    {
        $flags = \Filesystem_Iterator::CURRENT_AS_FILEINFO;
        $flags += \Filesystem_Iterator::SKIP_DOTS;
        foreach ($this->list_all() as $name) {
            $directory = $this->get_containing_directory_full_path($name);
            try {
                $dir_iterator = new \Filesystem_Iterator($directory, $flags);
            } catch (\UnexpectedValueException) {
                // FilesystemIterator throws an UnexpectedValueException if the
                // specified path is not a directory, or if it is not accessible.
                continue;
            }
            $directory_unlink = true;
            $directory_mtime = filemtime($directory);
            foreach ($dir_iterator as $fileinfo) {
                if ($directory_mtime > $fileinfo->get_m_time()) {
                    // Ensure the folder is writable.
                    @chmod($directory, 0777);
                    @unlink($fileinfo->get_path_name());
                } else {
                    // The directory still contains valid files.
                    $directory_unlink = false;
                }
            }
            if ($directory_unlink) {
                $this->unlink($name);
            }
        }
    }
    /**
     * Gets the full path of the file storage directory's parent.
     *
     * @param string $name
     *   The virtual file name. Can be a relative path.
     *
     * @return string
     *   The full path of the containing directory where the file is or should be
     *   stored.
     */
    protected function get_containing_directory_full_path($name)
    {
        // Remove the .php file extension from the directory name.
        // Within a single directory, a subdirectory cannot have the same name as a
        // file. Thus, when switching between MTimeProtectedFastFileStorage and
        // FileStorage, the subdirectory or the file cannot be created in case the
        // other file type exists already.
        if (str_ends_with($name, '.php')) {
            $name = substr($name, 0, -4);
        }
        return $this->directory . '/' . str_replace('/', '#', $name);
    }
    /**
     * Clears PHP's stat cache and returns the directory's mtime.
     */
    protected function get_uncached_m_time($directory): int|false
    {
        clearstatcache(true, $directory);
        return filemtime($directory);
    }
    /**
     * A brute force tempnam implementation supporting streams.
     *
     * @param string $directory
     *   The directory where the temporary filename will be created.
     * @param string $prefix
     *   The prefix of the generated temporary filename.
     *
     * @return string
     *   Returns the new temporary filename (with path), or FALSE on failure.
     */
    protected function tempnam(string $directory, string $prefix): string
    {
        do {
            $path = $directory . '/' . $prefix . Crypt::random_bytes_base64(20);
        } while (file_exists($path));
        return $path;
    }
}