<?php

declare (strict_types=1);
namespace Drupal\Core\Action;

use Drupal\Core\Plugin\Plugin_Base;
/**
 * Provides a base implementation for an Action plugin.
 *
 * @see \Drupal\Core\Annotation\Action
 * @see \Drupal\Core\Action\ActionManager
 * @see \Drupal\Core\Action\ActionInterface
 * @see plugin_api
 */
abstract class Action_Base extends Plugin_Base implements Action_Interface
{
    /**
     * {@inheritdoc}
     */
    public function execute_multiple(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->execute($entity);
        }
    }
}