<?php

namespace Drupal\contextual\Theme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Set the theme according to the parameter passed to the controller.
 */
final readonly class ContextualLinksNegotiator implements ThemeNegotiatorInterface {

  public function __construct(
    protected RouteMatchInterface $route_match,
    protected RequestStack $requestStack,
    protected ThemeHandlerInterface $themeHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  public function applies(RouteMatchInterface $route_match): bool {
    return $route_match->getRouteName() === 'contextual.render';
  }

  public function determineActiveTheme(RouteMatchInterface $route_match): string {
    $request = $this->requestStack->getCurrentRequest();
    $theme = $request?->query->get('theme', '') ?? '';

    if ($this->themeHandler->themeExists($theme)) {
      return $theme;
    }

    return $this->configFactory->get('system.theme')->get('default');
  }

}
