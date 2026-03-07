<?php

declare(strict_types=1);

namespace Drupal\announcements_feed;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Object containing a single announcement from the feed.
 *
 * @internal
 */
final readonly class Announcement
{
    /**
     * Construct an Announcement object.
     *
     * @param string $id
     *   Unique identifier of the announcement.
     * @param string $title
     *   Title of the announcement.
     * @param string $url
     *   URL where the announcement can be seen.
     * @param string $date_modified
     *   When was the announcement last modified.
     * @param string $date_published
     *   When was the announcement published.
     * @param string $content_html
     *   HTML content of the announcement.
     * @param string $version
     *   Target Drupal version of the announcement.
     * @param bool $featured
     *   Whether this announcement is featured or not.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $date_modified,
        public string $date_published,
        public string $content_html,
        public string $version,
        public bool $featured,
    ) {
    }

    /**
     * Returns the content of the announcement with no markup.
     *
     * @return string
     *   Content of the announcement without markup.
     */
    public function getContent(): string
    {
        return strip_tags($this->content_html);
    }

    /**
     * Gets the published date in timestamp format.
     *
     * @return int
     *   Date published timestamp.
     */
    public function getDatePublishedTimestamp()
    {
        return DrupalDateTime::createFromFormat(DATE_ATOM, $this->date_published)->getTimestamp();
    }

}
