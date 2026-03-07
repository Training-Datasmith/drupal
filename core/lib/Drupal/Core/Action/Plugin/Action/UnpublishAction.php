<?php

declare(strict_types=1);

namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\Derivative\EntityPublishedActionDeriver;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Unpublishes an entity.
 */
#[Action(
    id: 'entity:unpublish_action',
    action_label: new TranslatableMarkup('Unpublish'),
    deriver: EntityPublishedActionDeriver::class
)]
class UnpublishAction extends EntityActionBase
{
    /**
     * {@inheritdoc}
     */
    public function execute($entity = null): void
    {
        $entity->setUnpublished()->save();
    }

    /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = null, $return_as_object = false)
    {
        $key = $object->getEntityType()->getKey('published');

        /** @var \Drupal\Core\Entity\EntityInterface $object */
        $result = $object->access('update', $account, true)
          ->andIf($object->$key->access('edit', $account, true));

        return $return_as_object ? $result : $result->isAllowed();
    }

}
