<?php

namespace Drupal\system;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * System Manager Service.
 */
class SystemManager {

  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a SystemManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The menu tree manager.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrail
   *   The active menu trail service.
   */
  public function __construct(protected \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler, RequestStack $request_stack, protected \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree, protected \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrail) {
    $this->requestStack = $request_stack;
  }

  /**
   * Checks for requirement severity.
   *
   * @return bool
   *   Returns the status of the system.
   */
  public function checkRequirements(): bool {
    $requirements = $this->listRequirements();
    return RequirementSeverity::maxSeverityFromRequirements($requirements) === RequirementSeverity::Error;
  }

  /**
   * Displays the site status report. Can also be used as a pure check.
   *
   * @return array
   *   An array of system requirements.
   */
  public function listRequirements() {
    // Load .install files.
    foreach ($this->moduleHandler->getModuleList() as $module => $extension) {
      $this->moduleHandler->loadInclude($module, 'install');
    }

    // Check run-time requirements and status information.
    $requirements = $this->moduleHandler->invokeAll('requirements', ['runtime']);
    $runtime_requirements = $this->moduleHandler->invokeAll('runtime_requirements');
    $requirements = array_merge($requirements, $runtime_requirements);
    $this->moduleHandler->alter('requirements', $requirements);
    $this->moduleHandler->alter('runtime_requirements', $requirements);
    uasort($requirements, function (array $a, array $b) {
      if (!isset($a['weight'])) {
        if (!isset($b['weight'])) {
          return strcasecmp((string) $a['title'], (string) $b['title']);
        }
        return -$b['weight'];
      }
      return isset($b['weight']) ? $a['weight'] - $b['weight'] : $a['weight'];
    });

    return $requirements;
  }

  /**
   * Extracts the highest severity from the requirements array.
   *
   * @param array $requirements
   *   An array of requirements, in the same format as is returned by
   *   hook_requirements().
   *
   * @return int
   *   The highest severity in the array.
   *
   * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
   *   \Drupal\Core\Extension\Requirement\RequirementSeverity::getMaxSeverity()
   *   instead.
   *
   * @see https://www.drupal.org/node/3410939
   */
  public function getMaxSeverity(array &$requirements) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ' . RequirementSeverity::class . '::maxSeverityFromRequirements() instead. See https://www.drupal.org/node/3410939', \E_USER_DEPRECATED);
    return RequirementSeverity::maxSeverityFromRequirements($requirements)->value;
  }

  /**
   * Loads the contents of a menu block.
   *
   * This function is often a destination for these blocks.
   * For example, 'admin/structure/types' needs to have a destination to be
   * valid in the Drupal menu system, but too much information there might be
   * hidden, so we supply the contents of the block.
   *
   * @return array
   *   A render array suitable for
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function getBlockContents(): array {
    // We hard-code the menu name here since otherwise a link in the tools menu
    // or elsewhere could give us a blank block.
    $link = $this->menuActiveTrail->getActiveLink('admin');
    if ($link && $content = $this->getAdminBlock($link)) {
      return [
        '#theme' => 'admin_block_content',
        '#content' => $content,
      ];
    }
    return [
      '#markup' => $this->t('You do not have any administrative items.'),
    ];
  }

  /**
   * Provide a single block on the administration overview page.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $instance
   *   The menu item to be displayed.
   *
   * @return array
   *   An array of menu items, as expected by admin-block-content.html.twig.
   */
  public function getAdminBlock(MenuLinkInterface $instance): array {
    $content = [];
    // Only find the children of this link.
    $link_id = $instance->getPluginId();
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    foreach ($tree as $key => $element) {
      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        // @todo Bubble cacheability metadata of both accessible and
        //   inaccessible links. Currently made impossible by the way admin
        //   blocks are rendered.
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      $content[$key]['title'] = $link->getTitle();
      $content[$key]['options'] = $link->getOptions();
      $content[$key]['description'] = $link->getDescription();
      $content[$key]['url'] = $link->getUrlObject();
    }
    ksort($content);
    return $content;
  }

}
