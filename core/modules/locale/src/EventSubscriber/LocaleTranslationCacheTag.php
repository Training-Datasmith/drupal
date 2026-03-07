<?php

declare(strict_types=1);

namespace Drupal\locale\EventSubscriber;

use Drupal\locale\LocaleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber invalidating cache tags when translating a string.
 */
class LocaleTranslationCacheTag implements EventSubscriberInterface
{
    /**
     * Constructs a LocaleTranslationCacheTag object.
     *
     * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
     *   The cache tags invalidator.
     */
    public function __construct(protected \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator)
    {
    }

    /**
     * Invalidate cache tags whenever a string is translated.
     */
    public function saveTranslation(): void
    {
        $this->cacheTagsInvalidator->invalidateTags(['rendered', 'locale', 'library_info']);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        $events[LocaleEvents::SAVE_TRANSLATION][] = ['saveTranslation'];
        return $events;
    }

}
