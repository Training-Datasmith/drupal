<?php

declare (strict_types=1);
namespace Drupal\Component\Http_Foundation;

use Symfony\Component\Http_Foundation\Redirect_Response;
/**
 * Provides a common base class for safe redirects.
 *
 * In case you want to redirect to external URLs use
 * TrustedRedirectResponse.
 *
 * For local URLs we use LocalRedirectResponse which opts
 * out of external redirects.
 */
abstract class Secured_Redirect_Response extends Redirect_Response
{
    /**
     * Copies an existing redirect response into a safe one.
     *
     * The safe one cannot accidentally redirect to an external URL, unless
     * actively wanted (see TrustedRedirectResponse).
     *
     * @param \Symfony\Component\HttpFoundation\RedirectResponse $response
     *   The original redirect.
     *
     * @return static
     */
    public static function create_from_redirect_response(Redirect_Response $response)
    {
        $safe_response = new static($response->get_target_url(), $response->get_status_code(), $response->headers->all_preserve_case());
        $safe_response->from_response($response);
        return $safe_response;
    }
    /**
     * Copies over the values from the given response.
     *
     * @param \Symfony\Component\HttpFoundation\RedirectResponse $response
     *   The redirect response object.
     */
    protected function from_response(Redirect_Response $response)
    {
        $this->set_protocol_version($response->get_protocol_version());
        if ($response->get_charset()) {
            $this->set_charset($response->get_charset());
        }
        // Cookies are separate from other headers and have to be copied over
        // directly.
        foreach ($response->headers->get_cookies() as $cookie) {
            $this->headers->set_cookie($cookie);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function set_target_url($url): static
    {
        if (!$this->is_safe($url)) {
            throw new \InvalidArgumentException(sprintf('It is not safe to redirect to %s', $url));
        }
        return parent::set_target_url($url);
    }
    /**
     * Returns whether the URL is considered as safe to redirect to.
     *
     * @param string $url
     *   The URL checked for safety.
     *
     * @return bool
     *   Returns TRUE if the URL is safe, FALSE otherwise.
     */
    abstract protected function is_safe($url);
}