<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Event_Subscriber;

use Drupal\Core\Cache\Cache_Tags_Checksum_Interface;
use Drupal\Core\Cache\Cache_Tags_Checksum_Preload_Interface;
use Drupal\Core\Site\Settings;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Preloads frequently used cache tags.
 */
class Cache_Tag_Preload_Subscriber implements Event_Subscriber_Interface
{
    public function __construct(protected Cache_Tags_Checksum_Interface $cache_tags_checksum)
    {
    }
    /**
     * Preloads frequently used cache tags.
     *
     * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
     *   The request event.
     */
    public function on_request(Request_Event $event): void
    {
        if ($event->is_main_request() && $this->cache_tags_checksum instanceof Cache_Tags_Checksum_Preload_Interface) {
            $default_preload_cache_tags = array_merge(['route_match', 'access_policies', 'routes', 'router', 'entity_types', 'entity_field_info', 'entity_bundles', 'local_task', 'library_info', 'http_response'], Settings::get('cache_preload_tags', []));
            $this->cache_tags_checksum->register_cache_tags_for_preload($default_preload_cache_tags);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_subscribed_events(): array
    {
        $events[Kernel_Events::REQUEST][] = ['onRequest', 500];
        return $events;
    }
}