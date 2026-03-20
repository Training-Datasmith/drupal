<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database_Exception;
use Drupal\Core\Dependency_Injection\Dependency_Serialization_Trait;
/**
 * Defines the Database storage.
 */
class Database_Storage implements Storage_Interface
{
    use Dependency_Serialization_Trait;
    /**
     * Constructs a new DatabaseStorage.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   A Database connection to use for reading and writing configuration data.
     * @param string $table
     *   A database table name to store configuration data in.
     * @param array $options
     *   (optional) Any additional database connection options to use in queries.
     * @param string $collection
     *   (optional) The collection to store configuration in. Defaults to the
     *   default collection.
     */
    public function __construct(
        protected \Drupal\Core\Database\Connection $connection,
        /**
         * The database table name.
         */
        protected $table,
        protected array $options = [],
        /**
         * The storage collection.
         */
        protected $collection = Storage_Interface::DEFAULT_COLLECTION
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name): bool
    {
        try {
            return (bool) $this->connection->query_range('SELECT 1 FROM {' . $this->connection->escape_table($this->table) . '} WHERE [collection] = :collection AND [name] = :name', 0, 1, [':collection' => $this->collection, ':name' => $name], $this->options)->fetch_field();
        } catch (\Exception $e) {
            if ($this->connection->schema()->table_exists($this->table)) {
                throw $e;
            }
            // If we attempt a read without actually having the table available,
            // return false so the caller can handle it.
            return false;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        $data = false;
        try {
            $raw = $this->connection->query('SELECT [data] FROM {' . $this->connection->escape_table($this->table) . '} WHERE [collection] = :collection AND [name] = :name', [':collection' => $this->collection, ':name' => $name], $this->options)->fetch_field();
            if ($raw !== false) {
                $data = $this->decode($raw);
            }
        } catch (\Exception $e) {
            if ($this->connection->schema()->table_exists($this->table)) {
                throw $e;
            }
            // If we attempt a read without actually having the table available,
            // return false so the caller can handle it.
        }
        return $data;
    }
    /**
     * {@inheritdoc}
     */
    public function read_multiple(array $names)
    {
        if (empty($names)) {
            return [];
        }
        $list = [];
        try {
            $list = $this->connection->query('SELECT [name], [data] FROM {' . $this->connection->escape_table($this->table) . '} WHERE [collection] = :collection AND [name] IN ( :names[] )', [':collection' => $this->collection, ':names[]' => $names], $this->options)->fetch_all_keyed();
            foreach ($list as &$data) {
                $data = $this->decode($data);
            }
        } catch (\Exception $e) {
            if ($this->connection->schema()->table_exists($this->table)) {
                throw $e;
            }
            // If we attempt a read without actually having the table available,
            // return an empty array so the caller can handle it.
        }
        return $list;
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data)
    {
        $data = $this->encode($data);
        try {
            return $this->do_write($name, $data);
        } catch (\Exception $e) {
            // If there was an exception, try to create the table.
            if ($this->ensure_table_exists()) {
                return $this->do_write($name, $data);
            }
            // Some other failure that we can not recover from.
            throw new Storage_Exception($e->get_message(), 0, $e);
        }
    }
    /**
     * Helper method so we can re-try a write.
     *
     * @param string $name
     *   The config name.
     * @param string $data
     *   The config data, already dumped to a string.
     *
     * @return bool
     *   TRUE when the write was successful, FALSE otherwise.
     */
    protected function do_write($name, $data): bool
    {
        return (bool) $this->connection->merge($this->table, $this->options)->keys(['collection', 'name'], [$this->collection, $name])->fields(['data' => $data])->execute();
    }
    /**
     * Check if the config table exists and create it if not.
     *
     * @return bool
     *   TRUE if the table was created, FALSE otherwise.
     *
     * @throws \Drupal\Core\Config\StorageException
     *   If a database error occurs.
     */
    protected function ensure_table_exists(): bool
    {
        try {
            $this->connection->schema()->create_table($this->table, static::schema_definition());
        } catch (Database_Exception) {
            return true;
        } catch (\Exception) {
            return false;
        }
        return true;
    }
    /**
     * Defines the schema for the configuration table.
     *
     * @internal
     */
    protected static function schema_definition(): array
    {
        return ['description' => 'The base table for configuration data.', 'fields' => ['collection' => ['description' => 'Primary Key: Config object collection.', 'type' => 'varchar_ascii', 'length' => 255, 'not null' => true, 'default' => ''], 'name' => ['description' => 'Primary Key: Config object name.', 'type' => 'varchar_ascii', 'length' => 255, 'not null' => true, 'default' => ''], 'data' => ['description' => 'A serialized configuration object data.', 'type' => 'blob', 'not null' => false, 'size' => 'big']], 'primary key' => ['collection', 'name']];
    }
    /**
     * Implements Drupal\Core\Config\StorageInterface::delete().
     *
     * @throws \PDOException
     *
     * @todo Ignore replica targets for data manipulation operations.
     */
    public function delete($name): bool
    {
        return (bool) $this->connection->delete($this->table, $this->options)->condition('collection', $this->collection)->condition('name', $name)->execute();
    }
    /**
     * Implements Drupal\Core\Config\StorageInterface::rename().
     *
     * @throws \PDOException
     */
    public function rename($name, $new_name): bool
    {
        return (bool) $this->connection->update($this->table, $this->options)->fields(['name' => $new_name])->condition('name', $name)->condition('collection', $this->collection)->execute();
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data): string
    {
        return serialize($data);
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw): array|false
    {
        $data = @unserialize($raw, ['allowed_classes' => false]);
        return is_array($data) ? $data : false;
    }
    /**
     * {@inheritdoc}
     */
    public function list_all($prefix = '')
    {
        try {
            $query = $this->connection->select($this->table);
            $query->fields($this->table, ['name']);
            $query->condition('collection', $this->collection, '=');
            $query->condition('name', $prefix . '%', 'LIKE');
            $query->order_by('collection')->order_by('name');
            return $query->execute()->fetch_col();
        } catch (\Exception $e) {
            if ($this->connection->schema()->table_exists($this->table)) {
                throw $e;
            }
            // If we attempt a read without actually having the table available,
            // return an empty array so the caller can handle it.
            return [];
        }
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = ''): bool
    {
        try {
            return (bool) $this->connection->delete($this->table, $this->options)->condition('name', $prefix . '%', 'LIKE')->condition('collection', $this->collection)->execute();
        } catch (\Exception $e) {
            if ($this->connection->schema()->table_exists($this->table)) {
                throw $e;
            }
            // If we attempt a delete without actually having the table available,
            // return false so the caller can handle it.
            return false;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): static
    {
        return new static($this->connection, $this->table, $this->options, $collection);
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name()
    {
        return $this->collection;
    }
    /**
     * {@inheritdoc}
     */
    public function get_all_collection_names()
    {
        try {
            return $this->connection->query('SELECT DISTINCT [collection] FROM {' . $this->connection->escape_table($this->table) . '} WHERE [collection] <> :collection ORDER by [collection]', [':collection' => Storage_Interface::DEFAULT_COLLECTION])->fetch_col();
        } catch (\Exception $e) {
            if ($this->connection->schema()->table_exists($this->table)) {
                throw $e;
            }
            // If we attempt a read without actually having the table available,
            // return an empty array so the caller can handle it.
            return [];
        }
    }
}