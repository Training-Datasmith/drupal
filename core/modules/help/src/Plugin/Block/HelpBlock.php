<?php

namespace Drupal\help\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a 'Help' block.
 */
#[Block(
  id: "help_block",
  admin_label: new TranslatableMarkup("Help"),
  forms: ['settings_tray' => FALSE]
)]
class HelpBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Creates a HelpBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, protected \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler, protected \Drupal\Core\Routing\RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('module_handler'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   * @return mixed[]|array<int, non-empty-array>
   */
  public function build(): array {
    // Do not show on a 403 or 404 page.
    if ($this->request->attributes->has('exception')) {
      return [];
    }

    $build = [];
    $this->moduleHandler->invokeAllWith('help', function (callable $hook, string $module) use (&$build): void {
      // Don't add empty strings to $build array.
      if ($help = $hook($this->routeMatch->getRouteName(), $this->routeMatch)) {
        // Convert strings to #markup render arrays so that they will XSS admin
        // filtered.
        $build[] = is_array($help) ? $help : ['#markup' => $help];
      }
    });
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
