<?php

declare (strict_types=1);
namespace Drupal\Core\Breadcrumb;

/**
 * Breadcrumb theme preprocess.
 *
 * @internal
 */
class Breadcrumb_Preprocess
{
    /**
     * Prepares variables for breadcrumb templates.
     *
     * Default template: breadcrumb.html.twig.
     *
     * @param array $variables
     *   An associative array containing:
     *   - links: A list of \Drupal\Core\Link objects which should be rendered.
     */
    public function preprocess_breadcrumb(array &$variables): void
    {
        $variables['breadcrumb'] = [];
        /** @var \Drupal\Core\Link $link */
        foreach ($variables['links'] as $key => $link) {
            $variables['breadcrumb'][$key] = ['text' => $link->get_text(), 'url' => $link->get_url()->to_string()];
        }
    }
}