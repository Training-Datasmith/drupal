<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\Lock_Backend_Interface;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * The export storage manager dispatches an event for the export storage.
 *
 * This class is not meant to be extended and is final to make sure the
 * constructor and the getStorage method are both changed when this pattern is
 * used in other circumstances.
 */
final class Export_Storage_Manager implements Storage_Manager_Interface
{
    use Storage_Copy_Trait;
    /**
     * The name used to identify the lock.
     */
    public const LOCK_NAME = 'config_storage_export_manager';
    /**
     * The database storage.
     */
    protected \Drupal\Core\Config\Database_Storage $storage;
    /**
     * ExportStorageManager constructor.
     *
     * @param \Drupal\Core\Config\StorageInterface $active
     *   The active config storage to prime the export storage.
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *   The event dispatcher.
     * @param \Drupal\Core\Lock\LockBackendInterface $lock
     *   The used lock backend instance.
     */
    public function __construct(protected Storage_Interface $active, protected Connection $connection, protected Event_Dispatcher_Interface $event_dispatcher, protected Lock_Backend_Interface $lock)
    {
        // The point of this service is to provide the storage and dispatch the
        // event when needed, so the storage itself can not be a service.
        $this->storage = new Database_Storage($connection, 'config_export');
    }
    /**
     * {@inheritdoc}
     */
    public function get_storage(): \Drupal\Core\Config\Read_Only_Storage
    {
        // Acquire a lock for the request to assert that the storage does not change
        // when a concurrent request transforms the storage.
        if (!$this->lock->acquire(self::LOCK_NAME)) {
            $this->lock->wait(self::LOCK_NAME);
            if (!$this->lock->acquire(self::LOCK_NAME)) {
                throw new Storage_Transformer_Exception('Cannot acquire config export transformer lock.');
            }
        }
        // Wrapping the queries in a transaction for performance gain.
        $transaction = $this->connection->start_transaction();
        self::replace_storage_contents($this->active, $this->storage);
        unset($transaction);
        $this->event_dispatcher->dispatch(new Storage_Transform_Event($this->storage), Config_Events::STORAGE_TRANSFORM_EXPORT);
        return new Read_Only_Storage($this->storage);
    }
}