<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

/**
 * Base class for user-based cache contexts.
 *
 * Subclasses need to implement either
 * \Drupal\Core\Cache\Context\CacheContextInterface or
 * \Drupal\Core\Cache\Context\CalculatedCacheContextInterface.
 */
abstract class User_Cache_Context_Base
{
    /**
     * Constructs a new UserCacheContextBase class.
     *
     * @param \Drupal\Core\Session\AccountInterface $user
     *   The current user.
     */
    public function __construct(protected \Drupal\Core\Session\Account_Interface $user)
    {
    }
}