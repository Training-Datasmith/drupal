<?php

declare (strict_types=1);
namespace Drupal\Core\Authentication;

use Symfony\Component\Http_Foundation\Request;
/**
 * Generate a challenge when access is denied for unauthenticated users.
 *
 * On a 403 (access denied), if there are no credentials on the request, some
 * authentication methods (e.g. basic auth) require that a challenge is sent to
 * the client.
 */
interface Authentication_Provider_Challenge_Interface
{
    /**
     * Constructs an exception which is used to generate the challenge.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request.
     * @param \Exception $previous
     *   The previous exception.
     *
     * @return \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|null
     *   An exception to be used in order to generate an authentication challenge.
     */
    public function challenge_exception(Request $request, \Exception $previous);
}