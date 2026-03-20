<?php

declare(strict_types=1);

namespace Drupal\Core\Installer;

use Drupal\Core\Routing\RouteBuilder;

/**
 * Manages the router in the installer.
 */
class InstallerRouteBuilder extends RouteBuilder
{
    /**
     * {@inheritdoc}
     *
     * Overridden to return no routes.
     *
     * @todo Convert installer steps into routes; add an installer.routing.yml.
     */
    protected function getRouteDefinitions(): array
    {
        return [];
    }

}
