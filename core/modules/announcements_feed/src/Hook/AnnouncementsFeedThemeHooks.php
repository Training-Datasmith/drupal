<?php

declare(strict_types=1);

namespace Drupal\announcements_feed\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for announcements_feed.
 */
class AnnouncementsFeedThemeHooks
{
    /**
     * Implements hook_theme().
     */
    #[Hook('theme')]
    public function theme($existing, $type, $theme, $path): array
    {
        return [
          'announcements_feed' => [
            'variables' => [
              'featured' => null,
              'standard' => null,
              'count' => 0,
              'feed_link' => '',
            ],
          ],
          'announcements_feed_admin' => [
            'variables' => [
              'featured' => null,
              'standard' => null,
              'count' => 0,
              'feed_link' => '',
            ],
          ],
        ];
    }

}
