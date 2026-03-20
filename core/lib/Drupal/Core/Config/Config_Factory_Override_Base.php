<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Defines a base event listener implementation configuration overrides.
 */
abstract class Config_Factory_Override_Base implements Event_Subscriber_Interface
{
    /**
     * Reacts to the ConfigCollectionEvents::COLLECTION_INFO event.
     *
     * @param \Drupal\Core\Config\ConfigCollectionInfo $collection_info
     *   The configuration collection info event.
     */
    abstract public function add_collections(Config_Collection_Info $collection_info);
    /**
     * Actions to be performed to configuration override on configuration save.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The config CRUD event.
     */
    abstract public function on_config_save(Config_Crud_Event $event);
    /**
     * Actions to be performed to configuration override on configuration delete.
     *
     * @param \Drupal\Core\Config\ConfigCrudEvent $event
     *   The config CRUD event.
     */
    abstract public function on_config_delete(Config_Crud_Event $event);
    /**
     * Actions to be performed to configuration override on configuration rename.
     *
     * @param \Drupal\Core\Config\ConfigRenameEvent $event
     *   The config rename event.
     */
    abstract public function on_config_rename(Config_Rename_Event $event);
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Config_Collection_Events::COLLECTION_INFO][] = ['addCollections'];
        $events[Config_Events::SAVE][] = ['onConfigSave', 20];
        $events[Config_Events::DELETE][] = ['onConfigDelete', 20];
        $events[Config_Events::RENAME][] = ['onConfigRename', 20];
        return $events;
    }
    /**
     * Filters data in the override based on what is currently in configuration.
     *
     * @param \Drupal\Core\Config\Config $config
     *   Current configuration object.
     * @param \Drupal\Core\Config\StorableConfigBase $override
     *   Override object corresponding to the configuration to filter data in.
     */
    protected function filter_override(Config $config, Storable_Config_Base $override)
    {
        $override_data = $override->get();
        $changed = $this->filter_nested_array($config->get(), $override_data);
        if (empty($override_data)) {
            // If no override values are left that would apply, remove the override.
            $override->delete();
        } elseif ($changed) {
            // Otherwise set the filtered override values back.
            $override->set_data($override_data)->save(true);
        }
    }
    /**
     * Filters data in nested arrays.
     *
     * @param array $original_data
     *   Original data array to filter against.
     * @param array $override_data
     *   Override data to filter.
     *
     * @return bool
     *   TRUE if $override_data was changed, FALSE otherwise.
     */
    protected function filter_nested_array(array $original_data, array &$override_data)
    {
        $changed = false;
        foreach ($override_data as $key => $value) {
            if (!isset($original_data[$key])) {
                // The original data is not there anymore, remove the override.
                unset($override_data[$key]);
                $changed = true;
            } elseif (is_array($override_data[$key])) {
                if (is_array($original_data[$key])) {
                    // Do the filtering one level deeper.
                    // Ensure that we track $changed along the way.
                    if ($this->filter_nested_array($original_data[$key], $override_data[$key])) {
                        $changed = true;
                    }
                    // If no overrides are left under this level, remove the level.
                    if (empty($override_data[$key])) {
                        unset($override_data[$key]);
                        $changed = true;
                    }
                } else {
                    // The override is an array but the value is not, this will not go
                    // well, remove the override.
                    unset($override_data[$key]);
                    $changed = true;
                }
            }
        }
        return $changed;
    }
}