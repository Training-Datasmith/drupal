<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Importer;

use Drupal\Component\Event_Dispatcher\Event;
/**
 * Wraps a configuration event for event listeners.
 *
 * @see \Drupal\Core\Config\ConfigEvents::IMPORT_MISSING_CONTENT
 */
class Missing_Content_Event extends Event
{
    /**
     * Constructs a configuration import missing content event object.
     *
     * @param array $missingContent
     *   Missing content information.
     */
    public function __construct(protected array $missing_content)
    {
    }
    /**
     * Gets missing content information.
     *
     * @return array
     *   A list of missing content dependencies. The array is keyed by UUID. Each
     *   value is an array with the following keys: 'entity_type', 'bundle' and
     *   'uuid'.
     */
    public function get_missing_content()
    {
        return $this->missing_content;
    }
    /**
     * Resolves the missing content by removing it from the list.
     *
     * @param string $uuid
     *   The UUID of the content entity to mark resolved.
     *
     * @return $this
     *   The MissingContentEvent object.
     */
    public function resolve_missing_content($uuid): static
    {
        if (isset($this->missing_content[$uuid])) {
            unset($this->missing_content[$uuid]);
        }
        return $this;
    }
}