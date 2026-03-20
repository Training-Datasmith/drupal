<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Checkpoint;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\Config_Collection_Events;
use Drupal\Core\Config\Config_Crud_Event;
use Drupal\Core\Config\Config_Events;
use Drupal\Core\Config\Config_Rename_Event;
use Drupal\Core\Config\Storable_Config_Base;
use Drupal\Core\Config\Storage_Interface;
use Drupal\Core\Key_Value_Store\Key_Value_Factory_Interface;
use Drupal\Core\Key_Value_Store\Key_Value_Store_Interface;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Provides a config storage that can make checkpoints.
 *
 * This storage wraps the active storage, and provides the ability to take
 * checkpoints. Once a checkpoint has been created all configuration operations
 * made after the checkpoint will be recorded, so it is possible to revert to
 * original state when the checkpoint was taken.
 *
 * This class cannot be used to checkpoint another storage since it relies on
 * events triggered by the configuration system in order to work. It is the
 * responsibility of the caller to construct this class with the active storage.
 *
 * @internal
 *   This API is experimental.
 */
final class Checkpoint_Storage implements Checkpoint_Storage_Interface, Event_Subscriber_Interface, Logger_Aware_Interface
{
    use Logger_Aware_Trait;
    /**
     * Used as prefix to a config checkpoint collection.
     *
     * If this code is copied in order to checkpoint a different storage then
     * this value must be changed.
     */
    private const KEY_VALUE_COLLECTION_PREFIX = 'config.checkpoint.';
    /**
     * Used to store the list of collections in each checkpoint.
     *
     * Note this cannot be a valid configuration name.
     *
     * @see \Drupal\Core\Config\ConfigBase::validateName()
     */
    private const CONFIG_COLLECTION_KEY = 'collections';
    /**
     * The key value stores that store configuration changed for each checkpoint.
     *
     * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface[]
     */
    private array $key_value_stores;
    /**
     * The checkpoint to read from.
     */
    private ?Checkpoint $read_from_checkpoint = null;
    /**
     * Constructs a CheckpointStorage object.
     *
     * @param \Drupal\Core\Config\StorageInterface $activeStorage
     *   The active configuration storage.
     * @param \Drupal\Core\Config\Checkpoint\CheckpointListInterface $checkpoints
     *   The list of checkpoints.
     * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
     *   The key value factory.
     * @param string $collection
     *   (optional) The configuration collection.
     */
    public function __construct(private readonly Storage_Interface $active_storage, private readonly Checkpoint_List_Interface $checkpoints, private readonly Key_Value_Factory_Interface $key_value_factory, private readonly string $collection = Storage_Interface::DEFAULT_COLLECTION)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        if (count($this->checkpoints) === 0) {
            throw new No_Checkpoints_Exception();
        }
        foreach ($this->get_checkpoints_to_read_from() as $checkpoint) {
            $in_checkpoint = $this->get_key_value($checkpoint->id, $this->collection)->get($name);
            if ($in_checkpoint !== null) {
                // If $in_checkpoint is FALSE then the configuration has been deleted.
                return $in_checkpoint !== false;
            }
        }
        return $this->active_storage->exists($name);
    }
    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        $return = $this->read_multiple([$name]);
        return $return[$name] ?? false;
    }
    /**
     * {@inheritdoc}
     */
    public function read_multiple(array $names): array
    {
        if (count($this->checkpoints) === 0) {
            throw new No_Checkpoints_Exception();
        }
        $return = [];
        foreach ($this->get_checkpoints_to_read_from() as $checkpoint) {
            $return = array_merge($return, $this->get_key_value($checkpoint->id, $this->collection)->get_multiple($names));
            // Remove the read names from the list to fetch.
            $names = array_diff($names, array_keys($return));
            if (empty($names)) {
                // All the configuration has been read. Nothing more to do.
                break;
            }
        }
        // Names not found in the checkpoints have not been modified: read from
        // active storage.
        if (!empty($names)) {
            $return = array_merge($return, $this->active_storage->read_multiple($names));
        }
        // Remove any renamed or new configuration (FALSE has been recorded for
        // these operations in the checkpoint).
        // @see ::onConfigRename()
        // @see ::onConfigSaveAndDelete()
        return array_filter($return);
    }
    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $this->active_storage->encode($data);
    }
    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $this->active_storage->decode($raw);
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function list_all($prefix = ''): array
    {
        if (count($this->checkpoints) === 0) {
            throw new No_Checkpoints_Exception();
        }
        $names = $new_configuration = [];
        foreach ($this->get_checkpoints_to_read_from() as $checkpoint) {
            $checkpoint_names = array_keys(array_filter($this->get_key_value($checkpoint->id, $this->collection)->get_all(), function (mixed $value, string $name) use (&$new_configuration, $prefix): bool {
                if ($name === static::CONFIG_COLLECTION_KEY) {
                    return false;
                }
                // Remove any that don't start with the prefix.
                if ($prefix !== '' && !str_starts_with($name, $prefix)) {
                    return false;
                }
                // We've determined in a previous checkpoint that the configuration did
                // not exist.
                if (in_array($name, $new_configuration, true)) {
                    return false;
                }
                // If the value is FALSE then the configuration was created after the
                // checkpoint.
                if ($value === false) {
                    $new_configuration[] = $name;
                    return false;
                }
                return true;
            }, ARRAY_FILTER_USE_BOTH));
            $names = array_merge($names, $checkpoint_names);
        }
        // Remove any names that did not exist prior to the checkpoint.
        $active_names = array_diff($this->active_storage->list_all($prefix), $new_configuration);
        $names = array_unique(array_merge($names, $active_names));
        sort($names);
        return $names;
    }
    /**
     * {@inheritdoc}
     */
    public function create_collection($collection): self
    {
        $collection = new self($this->active_storage->create_collection($collection), $this->checkpoints, $this->key_value_factory, $collection);
        // \Drupal\Core\Config\Checkpoint\CheckpointStorage::$readFromCheckpoint is
        // assigned by reference so that it is  consistent across all collection
        // objects created from the same initial object.
        $collection->read_from_checkpoint =& $this->read_from_checkpoint;
        return $collection;
    }
    /**
     * {@inheritdoc}
     */
    public function get_all_collection_names(): array
    {
        $names = [];
        foreach ($this->get_checkpoints_to_read_from() as $checkpoint) {
            $names = array_merge($names, $this->get_key_value($checkpoint->id, Storage_Interface::DEFAULT_COLLECTION)->get(static::CONFIG_COLLECTION_KEY, []));
        }
        return array_unique(array_merge($this->active_storage->get_all_collection_names(), $names));
    }
    /**
     * {@inheritdoc}
     */
    public function get_collection_name(): string
    {
        return $this->collection;
    }
    /**
     * {@inheritdoc}
     */
    public function checkpoint(string|\Stringable $label): Checkpoint
    {
        // Generate a new ID based on the state of the current active checkpoint.
        $active_checkpoint = $this->checkpoints->get_active_checkpoint();
        if (!$active_checkpoint instanceof Checkpoint) {
            // @todo https://www.drupal.org/i/3408525 Consider options for generating
            //   a real fingerprint.
            $id = hash('sha1', random_bytes(32));
            return $this->checkpoints->add($id, $label);
        }
        // Determine if we need to create a new checkpoint by checking if
        // configuration has changed since the last checkpoint.
        $collections = $this->get_all_collection_names();
        $collections[] = Storage_Interface::DEFAULT_COLLECTION;
        foreach ($collections as $collection) {
            $current_checkpoint_data[$collection] = $this->get_key_value($active_checkpoint->id, $collection)->get_all();
            // Remove the collections key because it is irrelevant.
            unset($current_checkpoint_data[$collection][static::CONFIG_COLLECTION_KEY]);
            // If there is no data in the collection then there is no need to hash
            // the empty array.
            if (empty($current_checkpoint_data[$collection])) {
                unset($current_checkpoint_data[$collection]);
            }
        }
        if (!empty($current_checkpoint_data)) {
            // Use json_encode() here because it is both quicker and results in
            // smaller output than serialize().
            $id = hash('sha1', ($active_checkpoint->parent ?? '') . json_encode($current_checkpoint_data));
            return $this->checkpoints->add($id, $label);
        }
        $this->logger?->notice('A backup checkpoint was not created because nothing has changed since the "{active}" checkpoint was created.', ['active' => $active_checkpoint->label]);
        return $active_checkpoint;
    }
    /**
     * {@inheritdoc}
     */
    public function set_checkpoint_to_read_from(string|Checkpoint $checkpoint_id): static
    {
        if ($checkpoint_id instanceof Checkpoint) {
            $checkpoint_id = $checkpoint_id->id;
        }
        $this->read_from_checkpoint = $this->checkpoints->get($checkpoint_id);
        return $this;
    }
    /**
     * Gets the key value storage for the provided checkpoint.
     *
     * @param string $checkpoint
     *   The checkpoint to get the key value storage for.
     * @param string $collection
     *   The config collection to get the key value storage for.
     *
     * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     *   The key value storage for the provided checkpoint.
     */
    private function get_key_value(string $checkpoint, string $collection): Key_Value_Store_Interface
    {
        $checkpoint_key = $checkpoint;
        if ($collection !== Storage_Interface::DEFAULT_COLLECTION) {
            $checkpoint_key = $collection . '.' . $checkpoint_key;
        }
        return $this->key_value_stores[$checkpoint_key] ??= $this->key_value_factory->get(self::KEY_VALUE_COLLECTION_PREFIX . $checkpoint_key);
    }
    /**
     * Gets the checkpoints to read from.
     *
     * @return \Traversable<string, \Drupal\Core\Config\Checkpoint\Checkpoint>
     *   The checkpoints, keyed by ID.
     */
    private function get_checkpoints_to_read_from(): \Traversable
    {
        $checkpoint = $this->checkpoints->get_active_checkpoint();
        /** @var \Drupal\Core\Config\Checkpoint\Checkpoint[] $checkpoints_to_read_from */
        $checkpoints_to_read_from = [$checkpoint];
        if ($checkpoint->id !== $this->read_from_checkpoint?->id) {
            // Follow ancestors to find the checkpoint to start reading from.
            foreach ($this->checkpoints->get_parents($checkpoint->id) as $checkpoint) {
                array_unshift($checkpoints_to_read_from, $checkpoint);
                if ($checkpoint->id === $this->read_from_checkpoint?->id) {
                    break;
                }
            }
        }
        // Replay in parent to child order.
        foreach ($checkpoints_to_read_from as $checkpoint) {
            yield $checkpoint->id => $checkpoint;
        }
    }
    /**
     * Updates checkpoint when configuration is saved.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The configuration event.
     */
    public function on_config_save_and_delete(Config_Crud_Event $event): void
    {
        $active_checkpoint = $this->checkpoints->get_active_checkpoint();
        if ($active_checkpoint === null) {
            return;
        }
        $saved_config = $event->get_config();
        $collection = $saved_config->get_storage()->get_collection_name();
        $this->store_collection_name($collection);
        $key_value = $this->get_key_value($active_checkpoint->id, $collection);
        // If we have not yet stored a checkpoint for this configuration we should.
        if ($key_value->get($saved_config->get_name()) === null) {
            $original_data = $this->get_original_config($saved_config);
            // An empty array indicates that the config has to be new as a sequence
            // cannot be the root of a config object. We need to make this assumption
            // because $saved_config->isNew() will always return FALSE here.
            if (empty($original_data)) {
                $original_data = false;
            }
            // Only save change to state if there is a change, even if it's just keys
            // being re-ordered.
            if ($original_data !== $saved_config->get_raw_data()) {
                $key_value->set($saved_config->get_name(), $original_data);
            }
        }
    }
    /**
     * Updates checkpoint when configuration is saved.
     *
     * @param \Drupal\Core\Config\ConfigRenameEvent $event
     *   The configuration event.
     */
    public function on_config_rename(Config_Rename_Event $event): void
    {
        $active_checkpoint = $this->checkpoints->get_active_checkpoint();
        if ($active_checkpoint === null) {
            return;
        }
        $collection = $event->get_config()->get_storage()->get_collection_name();
        $this->store_collection_name($collection);
        $key_value = $this->get_key_value($active_checkpoint->id, $collection);
        $old_name = $event->get_old_name();
        // If we have not yet stored a checkpoint for this configuration, store a
        // complete copy of the original configuration. Note that renames do not
        // change data but storing the complete data allows
        // \Drupal\Core\Config\ConfigImporter to track renames using UUIDs.
        if ($key_value->get($old_name) === null) {
            $key_value->set($old_name, $this->get_original_config($event->get_config()));
        }
        // Record that the new name did not exist prior to the checkpoint.
        $new_name = $event->get_config()->get_name();
        if ($key_value->get($new_name) === null) {
            $key_value->set($new_name, false);
        }
    }
    /**
     * Gets the original data from the configuration.
     *
     * @param \Drupal\Core\Config\StorableConfigBase $config
     *   The config to get the original data from.
     *
     * @return mixed
     *   The original data.
     */
    private function get_original_config(Storable_Config_Base $config): mixed
    {
        if ($config instanceof Config) {
            return $config->get_original(apply_overrides: false);
        }
        return $config->get_original();
    }
    /**
     * Stores the collection name so the storage knows its own collections.
     *
     * @param string $collection
     *   The name of the collection.
     */
    private function store_collection_name(string $collection): void
    {
        // We do not need to store the default collection.
        if ($collection === Storage_Interface::DEFAULT_COLLECTION) {
            return;
        }
        $key_value = $this->get_key_value($this->checkpoints->get_active_checkpoint()->id, Storage_Interface::DEFAULT_COLLECTION);
        $collections = $key_value->get(static::CONFIG_COLLECTION_KEY, []);
        assert(is_array($collections));
        if (in_array($collection, $collections, true)) {
            return;
        }
        $collections[] = $collection;
        $key_value->set(static::CONFIG_COLLECTION_KEY, $collections);
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Config_Events::SAVE][] = 'onConfigSaveAndDelete';
        $events[Config_Events::DELETE][] = 'onConfigSaveAndDelete';
        $events[Config_Events::RENAME][] = 'onConfigRename';
        $events[Config_Collection_Events::SAVE_IN_COLLECTION][] = 'onConfigSaveAndDelete';
        $events[Config_Collection_Events::DELETE_IN_COLLECTION][] = 'onConfigSaveAndDelete';
        $events[Config_Collection_Events::RENAME_IN_COLLECTION][] = 'onConfigRename';
        return $events;
    }
    /**
     * {@inheritdoc}
     */
    public function write($name, array $data): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a CheckpointStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function delete($name): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a CheckpointStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a CheckpointStorage');
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all($prefix = ''): never
    {
        throw new \BadMethodCallException(__METHOD__ . ' is not allowed on a CheckpointStorage');
    }
}