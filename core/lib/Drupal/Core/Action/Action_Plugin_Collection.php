<?php

declare (strict_types=1);
namespace Drupal\Core\Action;

use Drupal\Core\Plugin\Default_Single_Lazy_Plugin_Collection;
/**
 * Provides a container for lazily loading Action plugins.
 */
class Action_Plugin_Collection extends Default_Single_Lazy_Plugin_Collection
{
    /**
     * {@inheritdoc}
     *
     * @return \Drupal\Core\Action\ActionInterface
     *   The action plugin instance.
     */
    public function &get($instance_id)
    {
        return parent::get($instance_id);
    }
}