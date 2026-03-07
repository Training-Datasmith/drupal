<?php

namespace Drupal\Core\Render\Element;

use Drupal\Core\Render\Attribute\RenderElement;

/**
 * Provides a render element for a form.
 */
#[RenderElement('form')]
class Form extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#method' => 'post',
      '#theme_wrappers' => ['form'],
    ];
  }

}
