<?php

namespace Drupal\views;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\ViewsPluginManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the cache backend factory.
 */
class ViewExecutableFactory {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new ViewExecutableFactory.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\views\ViewsData $viewsData
   *   The views data.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   * @param \Drupal\views\Plugin\ViewsPluginManager|null $displayPluginManager
   *   The display plugin manager.
   */
  public function __construct(protected \Drupal\Core\Session\AccountInterface $user, RequestStack $request_stack, protected \Drupal\views\ViewsData $viewsData, protected \Drupal\Core\Routing\RouteProviderInterface $routeProvider, protected ?ViewsPluginManager $displayPluginManager = NULL) {
    $this->requestStack = $request_stack;
    if ($this->displayPluginManager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $displayPluginManager argument is deprecated in drupal:10.3.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3410349', E_USER_DEPRECATED);
      $this->displayPluginManager = \Drupal::service('plugin.manager.views.display');
    }
  }

  /**
   * Instantiates a ViewExecutable class.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   A view entity instance.
   *
   * @return \Drupal\views\ViewExecutable
   *   A ViewExecutable instance.
   */
  public function get(ViewEntityInterface $view): \Drupal\views\ViewExecutable {
    $view_executable = new ViewExecutable($view, $this->user, $this->viewsData, $this->routeProvider, $this->displayPluginManager);
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      $view_executable->setRequest($request);
    }
    return $view_executable;
  }

}
