<?php

declare(strict_types=1);

namespace Drupal\jsonapi\ResourceType;

/**
 * Contains all events emitted during the resource type build process.
 *
 * @see \Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent
 * @see \Drupal\jsonapi\ResourceType\ResourceTypeRepository
 */
final class ResourceTypeBuildEvents
{
    /**
     * Emitted during the resource type build process.
     */
    public const BUILD = 'jsonapi.resource_type.build';

}
