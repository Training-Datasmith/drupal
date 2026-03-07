<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of formatters or widgets.
 */
class EntityDisplayPluginCollection extends DefaultLazyPluginCollection
{
    /**
     * The key within the plugin configuration that contains the plugin ID.
     *
     * @var string
     */
    protected $pluginKey = 'type';

}
