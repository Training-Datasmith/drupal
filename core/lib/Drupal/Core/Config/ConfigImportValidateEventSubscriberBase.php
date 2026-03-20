<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Core\String_Translation\String_Translation_Trait;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Defines a base event listener implementation for config sync validation.
 */
abstract class Config_Import_Validate_Event_Subscriber_Base implements Event_Subscriber_Interface
{
    use String_Translation_Trait;
    /**
     * Checks that the configuration synchronization is valid.
     *
     * @param ConfigImporterEvent $event
     *   The config import event.
     */
    abstract public function on_config_importer_validate(Config_Importer_Event $event);
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Config_Events::IMPORT_VALIDATE][] = ['onConfigImporterValidate', 20];
        return $events;
    }
}