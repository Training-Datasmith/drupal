<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Component\Serialization\Object_Aware_Serialization_Interface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
/**
 * Defines a default cache backend factory.
 */
class Database_Backend_Factory implements Cache_Factory_Interface
{
    /**
     * Constructs the DatabaseBackendFactory object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   Database connection.
     * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
     *   The cache tags checksum provider.
     * @param \Drupal\Core\Site\Settings $settings
     *   (optional) The site settings.
     * @param \Drupal\Component\Serialization\ObjectAwareSerializationInterface|null $serializer
     *   (optional) The serializer to use.
     * @param \Drupal\Component\Datetime\TimeInterface|null $time
     *   The time service.
     *
     * @throws \BadMethodCallException
     */
    public function __construct(protected \Drupal\Core\Database\Connection $connection, protected \Drupal\Core\Cache\Cache_Tags_Checksum_Interface $checksum_provider, protected Settings $settings, protected Object_Aware_Serialization_Interface $serializer, protected Time_Interface $time)
    {
    }
    /**
     * Gets DatabaseBackend for the specified cache bin.
     *
     * @param string $bin
     *   The cache bin for which the object is created.
     *
     * @return \Drupal\Core\Cache\DatabaseBackend
     *   The cache backend object for the specified cache bin.
     */
    public function get($bin): \Drupal\Core\Cache\Database_Backend
    {
        $max_rows = $this->get_max_rows_for_bin($bin);
        return new Database_Backend($this->connection, $this->checksum_provider, $bin, $this->serializer, $this->time, $max_rows);
    }
    /**
     * Gets the max rows for the specified cache bin.
     *
     * @param string $bin
     *   The cache bin for which the object is created.
     *
     * @return int
     *   The maximum number of rows for the given bin. Defaults to
     *   DatabaseBackend::DEFAULT_MAX_ROWS.
     */
    protected function get_max_rows_for_bin($bin)
    {
        $max_rows_settings = $this->settings->get('database_cache_max_rows');
        // First, look for a cache bin specific setting.
        if (isset($max_rows_settings['bins'][$bin])) {
            $max_rows = $max_rows_settings['bins'][$bin];
        } elseif (isset($max_rows_settings['default'])) {
            $max_rows = $max_rows_settings['default'];
        } else {
            // Fall back to the default max rows if nothing else is configured.
            $max_rows = Database_Backend::DEFAULT_MAX_ROWS;
        }
        return $max_rows;
    }
}