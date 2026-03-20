<?php

declare(strict_types=1);

namespace Drupal\layout_builder\Access;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Accessible class to allow access for inline blocks in the Layout Builder.
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutPreviewAccessAllowed implements AccessibleInterface
{
    /**
     * {@inheritdoc}
     */
    public function access($operation, ?AccountInterface $account = null, $return_as_object = false)
    {
        if ($operation === 'view') {
            return $return_as_object ? AccessResult::allowed() : true;
        }
        // The layout builder preview should only need 'view' access.
        return $return_as_object ? AccessResult::forbidden() : false;
    }

}
