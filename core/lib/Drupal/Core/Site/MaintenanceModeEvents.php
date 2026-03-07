<?php

declare(strict_types=1);

namespace Drupal\Core\Site;

/**
 * Defines events for maintenance mode.
 */
final class MaintenanceModeEvents
{
    /**
     * The name of the event fired when request is made in maintenance more.
     */
    public const MAINTENANCE_MODE_REQUEST = 'site.maintenance_mode_request';

}
