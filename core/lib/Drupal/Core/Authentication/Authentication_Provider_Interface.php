<?php

declare (strict_types=1);
namespace Drupal\Core\Authentication;

use Symfony\Component\Http_Foundation\Request;
/**
 * Interface for authentication providers.
 */
interface Authentication_Provider_Interface
{
    /**
     * Checks whether suitable authentication credentials are on the request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     *
     * @return bool
     *   TRUE if authentication credentials suitable for this provider are on the
     *   request, FALSE otherwise.
     */
    public function applies(Request $request);
    /**
     * Authenticates the user.
     *
     * @param \Symfony\Component\HttpFoundation\Request|null $request
     *   The request object.
     *
     * @return \Drupal\Core\Session\AccountInterface|null
     *   AccountInterface - in case of a successful authentication.
     *   NULL - in case where authentication failed.
     */
    public function authenticate(Request $request);
}