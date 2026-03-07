<?php

namespace Drupal\Core\Config;

/**
 * Provides an in memory configuration storage.
 */
class MemoryStorage implements StorageInterface {

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
  public function __construct(/**
   * The storage collection.
   */
  protected $collection = StorageInterface::DEFAULT_COLLECTION) {
    $this->config = new \ArrayObject();
    $this->config[$this->collection] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name): bool {
    return isset($this->config[$this->collection][$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    if ($this->exists($name)) {
      return $this->config[$this->collection][$name];
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names): array {
    return array_intersect_key($this->config[$this->collection], array_flip($names));
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data): bool {
    $this->config[$this->collection][$name] = $data;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name): bool {
    if (isset($this->config[$this->collection][$name])) {
      unset($this->config[$this->collection][$name]);
      // Remove the collection if it is empty.
      if (empty($this->config[$this->collection])) {
        $this->config->offsetUnset($this->collection);
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name): bool {
    if (!$this->exists($name)) {
      return FALSE;
    }
    $this->config[$this->collection][$new_name] = $this->config[$this->collection][$name];
    unset($this->config[$this->collection][$name]);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $raw;
  }

  /**
   * {@inheritdoc}
   * @return mixed[]
   */
  public function listAll($prefix = ''): array {
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
  public function deleteAll($prefix = '') {
    if (!$this->config->offsetExists($this->collection)) {
      // There's nothing to delete.
      return FALSE;
    }
    if ($prefix === '') {
      $this->config->offsetUnset($this->collection);
      return TRUE;
    }
    $success = FALSE;
    foreach (array_keys($this->config[$this->collection]) as $name) {
      if (str_starts_with((string) $name, $prefix)) {
        $success = TRUE;
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
  public function createCollection($collection): static {
    $collection = new static($collection);
    $collection->config = $this->config;
    return $collection;
  }

  /**
   * {@inheritdoc}
   * @return mixed[]
   */
  public function getAllCollectionNames(): array {
    $collection_names = [];
    foreach ($this->config as $collection_name => $data) {
      // Exclude the default collection and empty collections.
      if ($collection_name !== StorageInterface::DEFAULT_COLLECTION && !empty($data)) {
        $collection_names[] = $collection_name;
      }
    }
    sort($collection_names);

    return $collection_names;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->collection;
  }

}
