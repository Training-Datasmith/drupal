<?php

declare(strict_types=1);

namespace Drupal\views\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an abstract base class for all views plugin annotations.
 */
abstract class ViewsPluginAnnotationBase extends Plugin
{
    /**
     * Whether or not to register a theme function automatically.
     *
     * This property is optional and it does not need to be declared.
     *
     * @var bool
     */
    public $register_theme = true;

}
