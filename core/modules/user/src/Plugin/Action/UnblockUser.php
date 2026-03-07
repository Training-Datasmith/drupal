<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Unblocks a user.
 */
#[Action(
    id: 'user_unblock_user_action',
    label: new TranslatableMarkup('Unblock the selected users'),
    type: 'user'
)]
class UnblockUser extends ActionBase
{
    /**
     * {@inheritdoc}
     */
    public function execute($account = null): void
    {
        // Skip unblocking user if they are already unblocked.
        if ($account !== false && $account->isBlocked()) {
            $account->activate();
            $account->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = null, $return_as_object = false)
    {
        /** @var \Drupal\user\UserInterface $object */
        $access = $object->status->access('edit', $account, true)
          ->andIf($object->access('update', $account, true));

        return $return_as_object ? $access : $access->isAllowed();
    }

}
