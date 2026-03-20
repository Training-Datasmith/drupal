<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection;

use Drupal\Component\Dependency_Injection\Container as DrupalContainer;
/**
 * Extends the container to prevent serialization.
 */
class Container extends Drupal_Container
{
    /**
     * {@inheritdoc}
     */
    public function __sleep(): array
    {
        assert(false, 'The container was serialized.');
        return array_keys(get_object_vars($this));
    }
}