<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\Plugin\ObjectWithPluginCollectionInterface;

/**
 * Provides an interface for an object using a plugin collection.
 *
 * @see \Drupal\Component\Plugin\LazyPluginCollection
 *
 * @ingroup plugin_api
 */
interface EntityWithPluginCollectionInterface extends EntityInterface, ObjectWithPluginCollectionInterface
{
}
