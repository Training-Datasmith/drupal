<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin;

/**
 * Provides a trait with typical behavior for plugins which have forms.
 */
trait PluginWithFormsTrait
{
    /**
     * Implements \Drupal\Core\Plugin\PluginWithFormsInterface::getFormClass().
     */
    public function getFormClass($operation)
    {
        if (isset($this->getPluginDefinition()['forms'][$operation])) {
            return $this->getPluginDefinition()['forms'][$operation];
        }
        if ($operation === 'configure' && $this instanceof PluginFormInterface) {
            return static::class;
        }
        return null;
    }

    /**
     * Implements \Drupal\Core\Plugin\PluginWithFormsInterface::hasFormClass().
     */
    public function hasFormClass($operation): bool
    {
        return !empty($this->getFormClass($operation));
    }

}
