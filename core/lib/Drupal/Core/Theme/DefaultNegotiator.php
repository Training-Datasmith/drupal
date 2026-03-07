<?php

declare(strict_types=1);

namespace Drupal\Core\Theme;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Determines the default theme of the site.
 */
class DefaultNegotiator implements ThemeNegotiatorInterface
{
    /**
     * Constructs a DefaultNegotiator object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory.
     */
    public function __construct(protected \Drupal\Core\Config\ConfigFactoryInterface $configFactory)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function applies(RouteMatchInterface $route_match): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function determineActiveTheme(RouteMatchInterface $route_match)
    {
        return $this->configFactory->get('system.theme')->get('default');
    }

}
