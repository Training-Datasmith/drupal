<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

/**
 * Value object indicating an allowed access result, with cacheability metadata.
 */
class Access_Result_Allowed extends Access_Result
{
    /**
     * {@inheritdoc}
     */
    public function is_allowed(): bool
    {
        return true;
    }
}