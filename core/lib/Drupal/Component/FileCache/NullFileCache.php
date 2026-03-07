<?php

namespace Drupal\Component\FileCache;

/**
 * Null implementation for the file cache.
 */
class NullFileCache implements FileCacheInterface {

  /**
   * {@inheritdoc}
   */
  public function get($filepath): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $filepaths): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function set($filepath, $data) {
  }

  /**
   * {@inheritdoc}
   */
  public function delete($filepath) {
  }

}
