<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Provides an in memory configuration storage.
 */
class Memory_Storage implements Storage_Interface
{
    /**
     * The configuration, an object shared by reference across collections.
     */
    protected \ArrayObject $config;
    /**
     * Constructs a new MemoryStorage.
     *
     * @param string $collection
     *   (optional) The collection to store configuration in. Defaults to the
     *   default collection.
     */
    public function __construct(
        /**
         * The storage collection.
         */
        protected $collection = Storage_Interface::DEFAULT_COLLECTION
    )
    {
        $this->config = new \ArrayObject();
        $this->config[$this->collection] = [];
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name): bool
    {
        return isset($this->config[$this->collection][$name]);
    }
    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        if ($this->exists($name)) {
            return $this->config[$this->collection][$name];
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function read_multiple(array $names): array
    {
        return array_intersect_key($this->config[$this->collection], array_flip($names));
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data): bool
    {
        $this->config[$this->collection][$name] = $data;
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name): bool
    {
        if (isset($this->config[$this->collection][$name])) {
            unset($this->config[$this->collection][$name]);
            // Remove the collection if it is empty.
            if (empty($this->config[$this->collection])) {
                $this->config->offsetUnset($this->collection);
            }
            return true;
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name): bool
    {
        if (!$this->exists($name)) {
            return false;
        }
        $this->config[$this->collection][$new_name] = $this->config[$this->collection][$name];
        unset($this->config[$this->collection][$name]);
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $data;
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $raw;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function list_all($prefix = ''): array
    {
        if (empty($this->config[$this->collection])) {
            // If the collection is empty no keys are set.
            return [];
        }
        $names = array_keys($this->config[$this->collection]);
        if ($prefix !== '') {
            return array_filter($names, fn(int|string $name) => str_starts_with((string) $name, $prefix));
        }
        return $names;
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = '')
    {
        if (!$this->config->offsetExists($this->collection)) {
            // There's nothing to delete.
            return false;
        }
        if ($prefix === '') {
            $this->config->offsetUnset($this->collection);
            return true;
        }
        $success = false;
        foreach (array_keys($this->config[$this->collection]) as $name) {
            if (str_starts_with((string) $name, $prefix)) {
                $success = true;
                unset($this->config[$this->collection][$name]);
            }
        }
        // Remove the collection if it is empty.
        if (empty($this->config[$this->collection])) {
            $this->config->offsetUnset($this->collection);
        }
        return $success;
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): static
    {
        $collection = new static($collection);
        $collection->config = $this->config;
        return $collection;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_all_collection_names(): array
    {
        $collection_names = [];
        foreach ($this->config as $collection_name => $data) {
            // Exclude the default collection and empty collections.
            if ($collection_name !== Storage_Interface::DEFAULT_COLLECTION && !empty($data)) {
                $collection_names[] = $collection_name;
            }
        }
        sort($collection_names);
        return $collection_names;
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name()
    {
        return $this->collection;
    }
}