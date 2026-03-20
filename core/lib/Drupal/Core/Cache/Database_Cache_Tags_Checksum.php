<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database_Exception;
/**
 * Cache tags invalidations checksum implementation that uses the database.
 */
class Database_Cache_Tags_Checksum implements Cache_Tags_Checksum_Interface, Cache_Tags_Invalidator_Interface, Cache_Tags_Checksum_Preload_Interface, Cache_Tags_Purge_Interface
{
    use Cache_Tags_Checksum_Trait;
    /**
     * Constructs a DatabaseCacheTagsChecksum object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     */
    public function __construct(protected \Drupal\Core\Database\Connection $connection)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function do_invalidate_tags(array $tags)
    {
        try {
            foreach ($tags as $tag) {
                $this->connection->merge('cachetags')->insert_fields(['invalidations' => 1])->expression('invalidations', '[invalidations] + 1')->key('tag', $tag)->execute();
            }
        } catch (\Exception $e) {
            // Create the cache table, which will be empty. This fixes cases during
            // core install where cache tags are invalidated before the table is
            // created.
            if (!$this->ensure_table_exists()) {
                throw $e;
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function get_tag_invalidation_counts(array $tags)
    {
        try {
            return $this->connection->query('SELECT [tag], [invalidations] FROM {cachetags} WHERE [tag] IN ( :tags[] )', [':tags[]' => $tags])->fetch_all_keyed();
        } catch (\Exception $e) {
            // If the table does not exist yet, create.
            if (!$this->ensure_table_exists()) {
                throw $e;
            }
        }
        return [];
    }
    /**
     * {@inheritdoc}
     */
    public function purge(): void
    {
        try {
            $this->connection->truncate('cachetags')->execute();
        } catch (\Throwable $e) {
            // If the table does not exist yet, there is nothing to purge.
            if (!$this->ensure_table_exists()) {
                throw $e;
            }
        }
        $this->reset();
    }
    /**
     * Check if the cache tags table exists and create it if not.
     */
    protected function ensure_table_exists(): bool
    {
        try {
            $database_schema = $this->connection->schema();
            $schema_definition = $this->schema_definition();
            $database_schema->create_table('cachetags', $schema_definition);
        } catch (Database_Exception) {
        } catch (\Exception) {
            return false;
        }
        return true;
    }
    /**
     * Defines the schema for the {cachetags} table.
     *
     * @internal
     */
    public function schema_definition(): array
    {
        return ['description' => 'Cache table for tracking cache tag invalidations.', 'fields' => ['tag' => ['description' => 'Namespace-prefixed tag string.', 'type' => 'varchar_ascii', 'length' => 255, 'not null' => true, 'default' => ''], 'invalidations' => ['description' => 'Number incremented when the tag is invalidated.', 'type' => 'int', 'not null' => true, 'default' => 0]], 'primary key' => ['tag']];
    }
    /**
     * {@inheritdoc}
     */
    public function get_database_connection()
    {
        return $this->connection;
    }
}