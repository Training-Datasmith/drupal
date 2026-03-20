<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Session\Account_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Routing\Route;
/**
 * Access protection against CSRF attacks.
 */
class Csrf_Request_Header_Access_Check implements Access_Check_Interface
{
    /**
     * A string key that will used to designate the token used by this class.
     */
    public const TOKEN_KEY = 'X-CSRF-Token request header';
    /**
     * Constructs a new rest CSRF access check.
     *
     * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
     *   The session configuration.
     * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
     *   The token generator.
     */
    public function __construct(protected \Drupal\Core\Session\Session_Configuration_Interface $session_configuration, protected \Drupal\Core\Access\Csrf_Token_Generator $csrf_token)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function applies(Route $route)
    {
        $requirements = $route->get_requirements();
        if (array_key_exists('_csrf_request_header_token', $requirements)) {
            if (isset($requirements['_method'])) {
                // There could be more than one method requirement separated with '|'.
                $methods = explode('|', $requirements['_method']);
                // CSRF protection only applies to write operations, so we can filter
                // out any routes that require reading methods only.
                $write_methods = array_diff($methods, ['GET', 'HEAD', 'OPTIONS', 'TRACE']);
                if (empty($write_methods)) {
                    return false;
                }
            }
            // No method requirement given, so we run this access check to be on the
            // safe side.
            return true;
        }
    }
    /**
     * Checks access.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The currently logged in account.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    public function access(Request $request, Account_Interface $account)
    {
        $method = $request->get_method();
        // Read-only operations are always allowed.
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true)) {
            return Access_Result::allowed();
        }
        // This check only applies if
        // 1. the user was successfully authenticated and
        // 2. the request comes with a session cookie.
        if ($account->is_authenticated() && $this->session_configuration->has_session($request)) {
            if (!$request->headers->has('X-CSRF-Token')) {
                return Access_Result::forbidden()->set_reason('X-CSRF-Token request header is missing')->set_cache_max_age(0);
            }
            $csrf_token = $request->headers->get('X-CSRF-Token');
            // @todo Remove validate call using 'rest' in 8.3.
            //   Kept here for sessions active during update.
            if (!$this->csrf_token->validate($csrf_token, self::TOKEN_KEY) && !$this->csrf_token->validate($csrf_token, 'rest')) {
                return Access_Result::forbidden()->set_reason('X-CSRF-Token request header is invalid')->set_cache_max_age(0);
            }
        }
        // Let other access checkers decide if the request is legit.
        return Access_Result::allowed()->set_cache_max_age(0);
    }
}