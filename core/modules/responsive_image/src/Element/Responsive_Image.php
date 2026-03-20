<?php

declare(strict_types=1);

namespace Drupal\responsive_image\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a responsive image element.
 */
#[RenderElement('responsive_image')]
class ResponsiveImage extends RenderElementBase
{
    /**
     * {@inheritdoc}
     */
    public function getInfo(): array
    {
        return [
          '#theme' => 'responsive_image',
        ];
    }

}
