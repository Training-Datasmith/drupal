<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

/**
 * Thrown by config storage transformers if they cannot acquire a lock.
 *
 * @see \Drupal\Core\Config\ImportStorageTransformer
 * @see \Drupal\Core\Config\ExportStorageManager
 */
class Storage_Transformer_Exception extends \Exception
{
}