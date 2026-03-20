<?php

declare(strict_types=1);

namespace Drupal\content_moderation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The access control handler for the content_moderation_state entity type.
 *
 * @see \Drupal\content_moderation\Entity\ContentModerationState
 */
class ContentModerationStateAccessControlHandler extends EntityAccessControlHandler
{
    /**
     * {@inheritdoc}
     */
    public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account)
    {
        // ContentModerationState is an internal entity type. Access is denied for
        // viewing, updating, and deleting. In order to update an entity's
        // moderation state use its moderation_state field.
        return AccessResult::forbidden('ContentModerationState is an internal entity type.');
    }

    /**
     * {@inheritdoc}
     */
    protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = null)
    {
        // ContentModerationState is an internal entity type. Access is denied for
        // creating. In order to update an entity's moderation state use its
        // moderation_state field.
        return AccessResult::forbidden('ContentModerationState is an internal entity type.');
    }

}
