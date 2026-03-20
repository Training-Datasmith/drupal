<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Importer;

use Drupal\Core\Config\Config_Events;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * Final event subscriber to the missing content event.
 *
 * Ensure that all missing content dependencies are removed from the event so
 * the importer can complete.
 *
 * @see \Drupal\Core\Config\ConfigImporter::processMissingContent()
 */
class Final_Missing_Content_Subscriber implements Event_Subscriber_Interface
{
    /**
     * Handles the missing content event.
     *
     * @param \Drupal\Core\Config\Importer\MissingContentEvent $event
     *   The missing content event.
     */
    public function on_missing_content(Missing_Content_Event $event): void
    {
        foreach (array_keys($event->get_missing_content()) as $uuid) {
            $event->resolve_missing_content($uuid);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        // This should always be the final event as it will mark all content
        // dependencies as resolved.
        $events[Config_Events::IMPORT_MISSING_CONTENT][] = ['onMissingContent', -1024];
        return $events;
    }
}