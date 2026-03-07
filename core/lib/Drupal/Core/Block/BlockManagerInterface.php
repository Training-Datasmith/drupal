<?php

declare(strict_types=1);

namespace Drupal\Core\Block;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextAwarePluginManagerInterface;
use Drupal\Core\Plugin\FilteredPluginManagerInterface;

/**
 * Provides an interface for the discovery and instantiation of block plugins.
 */
interface BlockManagerInterface extends ContextAwarePluginManagerInterface, CategorizingPluginManagerInterface, FilteredPluginManagerInterface
{
}
