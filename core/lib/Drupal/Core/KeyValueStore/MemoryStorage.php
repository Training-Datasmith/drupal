<?php

namespace Drupal\Core\KeyValueStore;

/**
 * Defines a default key/value store implementation.
 */
class MemoryStorage extends StorageBase {

  /**
   * The actual storage of key-value pairs.
   *
   * @var array
   */
  protected $data = [];

  /**
   * {@inheritdoc}
   */
  public function has($key): bool {
    return array_key_exists($key, $this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL) {
    return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys): array {
    return array_intersect_key($this->data, array_flip($keys));
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value): void {
    $this->data[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value): bool {
    if (!isset($this->data[$key])) {
      $this->data[$key] = $value;
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $data): void {
    $this->data = $data + $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function rename($key, $new_key): void {
    if ($key !== $new_key) {
      $this->data[$new_key] = $this->data[$key];
      unset($this->data[$key]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key): void {
    unset($this->data[$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys): void {
    foreach ($keys as $key) {
      unset($this->data[$key]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->data = [];
  }

}
