<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Blocks a user.
 */
#[Action(
    id: 'user_block_user_action',
    label: new TranslatableMarkup('Block the selected users'),
    type: 'user'
)]
class BlockUser extends ActionBase
{
    /**
     * {@inheritdoc}
     */
    public function execute($account = null): void
    {
        // Skip blocking user if they are already blocked.
        if ($account !== false && $account->isActive()) {
            // For efficiency manually save the original account before applying any
            // changes.
            $account->setOriginal(clone $account);
            $account->block();
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
