<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

/**
 * Provides a redirect response which cannot redirect to an external URL.
 */
class LocalRedirectResponse extends CacheableSecuredRedirectResponse
{
    use LocalAwareRedirectResponseTrait {
        LocalAwareRedirectResponseTrait::isLocal as isSafe;
    }

}
