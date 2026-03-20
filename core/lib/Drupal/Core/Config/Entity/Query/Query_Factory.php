<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity\Query;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\Config_Crud_Event;
use Drupal\Core\Config\Config_Events;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Config\Entity\Config_Entity_Type_Interface;
use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Entity\Query\Query_Base;
use Drupal\Core\Entity\Query\Query_Exception;
use Drupal\Core\Entity\Query\Query_Factory_Interface;
use Drupal\Core\Key_Value_Store\Key_Value_Factory_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Provides a factory for creating entity query objects for the config backend.
 */
class Query_Factory implements Query_Factory_Interface, Event_Subscriber_Interface
{
    /**
     * The prefix for the key value collection for fast lookups.
     */
    public const CONFIG_LOOKUP_PREFIX = 'config.entity.key_store.';
    /**
     * The namespace of this class, the parent class etc.
     *
     * @var array
     */
    protected $namespaces;
    /**
     * Constructs a QueryFactory object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config storage used by the config entity query.
     * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
     *   The key value factory.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The configuration manager.
     */
    public function __construct(protected \Drupal\Core\Config\Config_Factory_Interface $config_factory, protected Key_Value_Factory_Interface $key_value_factory, protected Config_Manager_Interface $config_manager)
    {
        $this->namespaces = Query_Base::get_namespaces($this);
    }
    /**
     * {@inheritdoc}
     */
    public function get(Entity_Type_Interface $entity_type, $conjunction): \Drupal\Core\Config\Entity\Query\Query
    {
        return new Query($entity_type, $conjunction, $this->config_factory, $this->key_value_factory, $this->namespaces);
    }
    /**
     * {@inheritdoc}
     */
    public function get_aggregate(Entity_Type_Interface $entity_type, $conjunction): never
    {
        throw new Query_Exception('Aggregation over configuration entities is not supported');
    }
    /**
     * Gets the key value store used to store fast lookups.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type.
     *
     * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     *   The key value store used to store fast lookups.
     */
    protected function get_config_key_store(Entity_Type_Interface $entity_type)
    {
        return $this->key_value_factory->get(static::CONFIG_LOOKUP_PREFIX . $entity_type->id());
    }
    /**
     * Updates or adds lookup data.
     *
     * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type
     *   The entity type.
     * @param \Drupal\Core\Config\Config $config
     *   The configuration object that is being saved.
     */
    protected function update_config_key_store(Config_Entity_Type_Interface $entity_type, Config $config)
    {
        $config_key_store = $this->get_config_key_store($entity_type);
        foreach ($entity_type->get_lookup_keys() as $lookup_key) {
            foreach ($this->get_keys($config, $lookup_key, 'get', $entity_type) as $key) {
                $values = $config_key_store->get($key, []);
                if (!in_array($config->get_name(), $values, true)) {
                    $values[] = $config->get_name();
                    $config_key_store->set($key, $values);
                }
            }
        }
    }
    /**
     * Deletes lookup data.
     *
     * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type
     *   The entity type.
     * @param \Drupal\Core\Config\Config $config
     *   The configuration object that is being deleted.
     */
    protected function delete_config_key_store(Config_Entity_Type_Interface $entity_type, Config $config)
    {
        $config_key_store = $this->get_config_key_store($entity_type);
        foreach ($entity_type->get_lookup_keys() as $lookup_key) {
            foreach ($this->get_keys($config, $lookup_key, 'getOriginal', $entity_type) as $key) {
                $values = $config_key_store->get($key, []);
                $pos = array_search($config->get_name(), $values, true);
                if ($pos !== false) {
                    unset($values[$pos]);
                }
                if (empty($values)) {
                    $config_key_store->delete($key);
                } else {
                    $config_key_store->set($key, $values);
                }
            }
        }
    }
    /**
     * Creates lookup keys for configuration data.
     *
     * @param \Drupal\Core\Config\Config $config
     *   The configuration object.
     * @param string $key
     *   The configuration key to look for.
     * @param string $get_method
     *   Which method on the config object to call to get the value. Either 'get'
     *   or 'getOriginal'.
     * @param \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type
     *   The configuration entity type.
     *
     * @return array
     *   An array of lookup keys concatenated to the configuration values.
     *
     * @throws \Drupal\Core\Config\Entity\Query\InvalidLookupKeyException
     *   The provided $key cannot end with a wildcard. This makes no sense since
     *   you cannot do fast lookups against this.
     */
    protected function get_keys(Config $config, $key, $get_method, Config_Entity_Type_Interface $entity_type): array
    {
        if (str_ends_with($key, '*')) {
            throw new Invalid_Lookup_Key_Exception(strtr('%entity_type lookup key %key ends with a wildcard this can not be used as a lookup', ['%entity_type' => $entity_type->id(), '%key' => $key]));
        }
        $parts = explode('.*', $key);
        // Remove leading dots.
        array_walk($parts, function (&$value): void {
            $value = trim($value, '.');
        });
        $values = (array) $this->get_values($config, $parts[0], $get_method, $parts);
        $output = [];
        // Flatten the array to a single dimension and add the key to all the
        // values.
        array_walk_recursive($values, function ($current) use (&$output, $key): void {
            if (is_scalar($current)) {
                $current = $key . ':' . $current;
            }
            $output[] = $current;
        });
        return $output;
    }
    /**
     * Finds all the values for a configuration key in a configuration object.
     *
     * @param \Drupal\Core\Config\Config $config
     *   The configuration object.
     * @param string $key
     *   The current key being checked.
     * @param string $get_method
     *   Which method on the config object to call to get the value.
     * @param array $parts
     *   All the parts of a configuration key we are checking.
     * @param int $start
     *   Which position of $parts we are processing. Defaults to 0.
     *
     * @return array|null
     *   The array of configuration values the match the provided key. NULL if
     *   the configuration object does not have a value that corresponds to the
     *   key.
     */
    protected function get_values(Config $config, string $key, $get_method, array $parts, $start = 0)
    {
        $value = $config->{$get_method}($key);
        if (is_array($value)) {
            $new_value = [];
            $start++;
            if (!isset($parts[$start])) {
                // The configuration object does not have a value that corresponds to
                // the key.
                return null;
            }
            foreach (array_keys($value) as $key_bit) {
                $new_key = $key . '.' . $key_bit;
                if (!empty($parts[$start])) {
                    $new_key .= '.' . $parts[$start];
                }
                $new_value[] = $this->get_values($config, $new_key, $get_method, $parts, $start);
            }
            $value = $new_value;
        }
        return $value;
    }
    /**
     * Updates configuration entity in the key store.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The configuration event.
     */
    public function on_config_save(Config_Crud_Event $event): void
    {
        $saved_config = $event->get_config();
        $entity_type_id = $this->config_manager->get_entity_type_id_by_name($saved_config->get_name());
        if ($entity_type_id) {
            $entity_type = $this->config_manager->get_entity_type_manager()->get_definition($entity_type_id);
            $this->update_config_key_store($entity_type, $saved_config);
        }
    }
    /**
     * Removes configuration entity from key store.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The configuration event.
     */
    public function on_config_delete(Config_Crud_Event $event): void
    {
        $saved_config = $event->get_config();
        $entity_type_id = $this->config_manager->get_entity_type_id_by_name($saved_config->get_name());
        if ($entity_type_id) {
            $entity_type = $this->config_manager->get_entity_type_manager()->get_definition($entity_type_id);
            $this->delete_config_key_store($entity_type, $saved_config);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Config_Events::SAVE][] = ['onConfigSave', 128];
        $events[Config_Events::DELETE][] = ['onConfigDelete', 128];
        return $events;
    }
}