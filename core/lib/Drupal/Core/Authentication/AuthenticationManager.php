<?php

declare (strict_types=1);
namespace Drupal\Core\Authentication;

use Drupal\Core\Routing\Route_Match;
use Symfony\Component\Http_Foundation\Request;
/**
 * Manager for authentication.
 *
 * On each request, let all authentication providers try to authenticate the
 * user. The providers are iterated according to their priority and the first
 * provider detecting credentials for its method wins. No further provider will
 * get triggered.
 *
 * If no provider sets an active user then the user remains anonymous.
 */
class Authentication_Manager implements Authentication_Provider_Interface, Authentication_Provider_Filter_Interface, Authentication_Provider_Challenge_Interface
{
    /**
     * Creates a new authentication manager instance.
     *
     * @param \Drupal\Core\Authentication\AuthenticationCollectorInterface $authCollector
     *   The authentication provider collector.
     */
    public function __construct(protected \Drupal\Core\Authentication\Authentication_Collector_Interface $auth_collector)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function applies(Request $request): bool
    {
        return (bool) $this->get_provider($request);
    }
    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request)
    {
        $provider_id = $this->get_provider($request);
        if ($provider_id === null) {
            return null;
        }
        $provider = $this->auth_collector->get_provider($provider_id);
        if ($provider === null) {
            return null;
        }
        return $provider->authenticate($request);
    }
    /**
     * {@inheritdoc}
     */
    public function applies_to_routed_request(Request $request, $authenticated)
    {
        $result = false;
        if ($authenticated) {
            $result = $this->apply_filter($request, $authenticated, $this->get_provider($request));
        } else {
            foreach ($this->auth_collector->get_sorted_providers() as $provider_id => $provider) {
                if ($this->apply_filter($request, $authenticated, $provider_id)) {
                    $result = true;
                    break;
                }
            }
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function challenge_exception(Request $request, \Exception $previous)
    {
        $provider_id = $this->get_challenger($request);
        if ($provider_id) {
            $provider = $this->auth_collector->get_provider($provider_id);
            return $provider->challenge_exception($request, $previous);
        }
    }
    /**
     * Returns the id of the authentication provider for a request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     *
     * @return string|null
     *   The id of the first authentication provider which applies to the request.
     *   If no application detects appropriate credentials, then NULL is returned.
     */
    protected function get_provider(Request $request)
    {
        foreach ($this->auth_collector->get_sorted_providers() as $provider_id => $provider) {
            if ($provider->applies($request)) {
                return $provider_id;
            }
        }
    }
    /**
     * Returns the ID of the challenge provider for a request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     *
     * @return string|null
     *   The ID of the first authentication provider which applies to the request.
     *   If no application detects appropriate credentials, then NULL is returned.
     */
    protected function get_challenger(Request $request)
    {
        foreach ($this->auth_collector->get_sorted_providers() as $provider_id => $provider) {
            if ($provider instanceof Authentication_Provider_Challenge_Interface && !$provider->applies($request) && $this->apply_filter($request, false, $provider_id)) {
                return $provider_id;
            }
        }
    }
    /**
     * Checks whether a provider is allowed on the given request.
     *
     * If no filter is registered for the given provider id, the default filter
     * is applied.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param bool $authenticated
     *   Whether or not the request is authenticated.
     * @param string $provider_id
     *   The id of the authentication provider to check access for.
     *
     * @return bool
     *   TRUE if provider is allowed, FALSE otherwise.
     */
    protected function apply_filter(Request $request, $authenticated, $provider_id)
    {
        $provider = $this->auth_collector->get_provider($provider_id);
        if ($provider && $provider instanceof Authentication_Provider_Filter_Interface) {
            return $provider->applies_to_routed_request($request, $authenticated);
        }
        return $this->default_filter($request, $provider_id);
    }
    /**
     * Default implementation of the provider filter.
     *
     * Checks whether a provider is allowed as per the _auth option on a route. If
     * the option is not set or if the request did not match any route, only
     * providers from the global provider set are allowed.
     *
     * If no filter is registered for the given provider id, the default filter
     * is applied.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming request.
     * @param string $provider_id
     *   The id of the authentication provider to check access for.
     *
     * @return bool
     *   TRUE if provider is allowed, FALSE otherwise.
     */
    protected function default_filter(Request $request, $provider_id)
    {
        $route = Route_Match::create_from_request($request)->get_route_object();
        $has_auth_option = isset($route) && $route->has_option('_auth');
        if ($has_auth_option) {
            return in_array($provider_id, $route->get_option('_auth'));
        }
        return $this->auth_collector->is_global($provider_id);
    }
}