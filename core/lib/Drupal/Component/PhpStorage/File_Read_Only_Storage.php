<?php

declare (strict_types=1);
namespace Drupal\Component\Php_Storage;

/**
 * Reads code as regular PHP files, but won't write them.
 */
class File_Read_Only_Storage implements Php_Storage_Interface
{
    /**
     * The directory where the files should be stored.
     */
    protected string $directory;
    /**
     * Constructs this FileStorage object.
     *
     * @param string[] $configuration
     *   An associative array, containing at least two keys (the rest are
     *   ignored):
     *   - directory: The directory where the files should be stored.
     *   - bin: The storage bin. Multiple storage objects can be instantiated with
     *   the same configuration, but for different bins.
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
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name): bool
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function get_full_path($name): string
    {
        return $this->directory . '/' . $name;
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all(): bool
    {
        return false;
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