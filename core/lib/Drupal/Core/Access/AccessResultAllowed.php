<?php

declare(strict_types=1);

namespace Drupal\Core\Access;

/**
 * Value object indicating an allowed access result, with cacheability metadata.
 */
class AccessResultAllowed extends AccessResult
{
    /**
     * {@inheritdoc}
     */
    public function isAllowed(): bool
    {
        return true;
    }

}
