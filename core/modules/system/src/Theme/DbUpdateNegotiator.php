<?php

declare(strict_types=1);

namespace Drupal\system\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Sets the active theme for the database update pages.
 */
class DbUpdateNegotiator implements ThemeNegotiatorInterface
{
    /**
     * Constructs a DbUpdateNegotiator.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory.
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
     *   The theme handler.
     */
    public function __construct(protected \Drupal\Core\Config\ConfigFactoryInterface $configFactory, protected \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function applies(RouteMatchInterface $route_match): bool
    {
        return $route_match->getRouteName() == 'system.db_update';
    }

    /**
     * {@inheritdoc}
     */
    public function determineActiveTheme(RouteMatchInterface $route_match): string
    {
        // The update page always uses Claro to ensure stability.
        return 'claro';
    }

}
