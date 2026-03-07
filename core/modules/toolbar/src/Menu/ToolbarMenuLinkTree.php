<?php

namespace Drupal\toolbar\Menu;

use Drupal\Core\Menu\MenuLinkTree;

/**
 * Extends MenuLinkTree to add specific theme suggestions for the toolbar.
 */
class ToolbarMenuLinkTree extends MenuLinkTree {

  /**
   * {@inheritdoc}
   */
  public function build(array $tree, $level = 0): array {
    if ($level == 0) {
      if (!$tree) {
        return [];
      }
      $build = parent::build($tree);

      $first_link = reset($tree)->link;
      // Get the menu name of the first link.
      $menu_name = $first_link->getMenuName();
      // Add a more specific theme suggestion to differentiate this rendered
      // menu from others.
      $build['#menu_name'] = $menu_name;
      $build['#theme'] = 'menu__toolbar__' . strtr($menu_name, '-', '_');
      return $build;
    }
    return parent::build($tree);
  }

}
