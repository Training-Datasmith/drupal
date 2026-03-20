<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext as SymfonyRequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * A router class for Drupal with access check and upcasting.
 */
class AccessAwareRouter implements AccessAwareRouterInterface
{
    /**
     * The router doing the actual routing.
     *
     * @var \Symfony\Component\Routing\RouterInterface
     */
    protected $router;

    /**
     * Constructs a router for Drupal with access check and upcasting.
     *
     * @param \Symfony\Component\Routing\RouterInterface $router
     *   The router doing the actual routing.
     * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
     *   The access manager.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The account to use in access checks.
     */
    public function __construct(RouterInterface $router, protected \Drupal\Core\Access\AccessManagerInterface $accessManager, protected \Drupal\Core\Session\AccountInterface $account)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $name, array $arguments)
    {
        // Ensure to call every other function to the router.
        return call_user_func_array([$this->router, $name], $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function setContext(SymfonyRequestContext $context): void
    {
        $this->router->setContext($context);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): SymfonyRequestContext
    {
        return $this->router->getContext();
    }

    /**
     * Matches the given request to a route and performs an access check.
     *
     * Delegates route matching to the decorated Symfony router, then runs
     * Drupal's access manager against the resolved route and its parameters.
     * If access is denied, an HTTP 403 exception is thrown. If the response
     * is cacheable (GET/HEAD), a CacheableAccessDeniedHttpException is thrown
     * instead so that the Page Cache can cache the 403 response.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The incoming HTTP request to match and access-check.
     *
     * @return array<string, mixed>
     *   The route parameters after access checking. The array includes all
     *   request attributes set by both the router and the access check.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     *   Thrown when the current user does not have access to the matched route.
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     *   Thrown when no route matches the request.
     *
     * @since 8.0.0
     */
    public function matchRequest(Request $request): array
    {
        $parameters = $this->router->matchRequest($request);
        $request->attributes->add($parameters);
        $this->checkAccess($request);
        // We can not return $parameters because the access check can change the
        // request attributes.
        return $request->attributes->all();
    }

    /**
     * Runs the access manager against the route and parameters in the request.
     *
     * The access result is stored as a request attribute under the key
     * AccessAwareRouterInterface::ACCESS_RESULT. A previously stored result
     * (e.g. from a master request) will not be overwritten by a subrequest.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request whose route and parameters will be access-checked. The
     *   ACCESS_RESULT attribute will be set on this request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     *   Thrown if the access result is not allowed.
     * @throws \Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException
     *   Thrown instead for cacheable GET/HEAD requests that are denied.
     *
     * @since 8.0.0
     */
    protected function checkAccess(Request $request)
    {
        // The cacheability (if any) of this request's access check result must be
        // applied to the response.
        $access_result = $this->accessManager->checkRequest($request, $this->account, true);
        // Allow a master request to set the access result for a subrequest: if an
        // access result attribute is already set, don't overwrite it.
        if (!$request->attributes->has(AccessAwareRouterInterface::ACCESS_RESULT)) {
            $request->attributes->set(AccessAwareRouterInterface::ACCESS_RESULT, $access_result);
        }
        if (!$access_result->isAllowed()) {
            if ($access_result instanceof CacheableDependencyInterface && $request->isMethodCacheable()) {
                throw new CacheableAccessDeniedHttpException($access_result, $access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : '');
            }
            throw new AccessDeniedHttpException($access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : '');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    /**
     * Generates a URL for a named route with the given parameters.
     *
     * Delegates directly to the decorated Symfony router. No access check is
     * performed during URL generation — generation is purely structural.
     *
     * @param string $name
     *   The route name (e.g. 'entity.node.canonical').
     * @param array<string, mixed> $parameters
     *   An array of route parameters to substitute into the route pattern.
     * @param int $referenceType
     *   One of UrlGeneratorInterface::ABSOLUTE_URL, ABSOLUTE_PATH,
     *   RELATIVE_PATH, or NETWORK_PATH. Defaults to ABSOLUTE_PATH.
     *
     * @return string
     *   The generated URL string.
     *
     * @since 8.0.0
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }

    /**
     * Matches a raw path string to a route and performs an access check.
     *
     * Converts the path string to a synthetic Request object and delegates to
     * matchRequest(). Prefer matchRequest() when a real Request is available.
     *
     * @param string $pathinfo
     *   The URL path to match, e.g. '/node/1'.
     *
     * @return array<string, mixed>
     *   The matched route parameters after access checking.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     *   Thrown when the current user does not have access to the matched route.
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     *   Thrown when the path does not match any known route, including when
     *   the raw path causes a BadRequestException (e.g. malformed UTF-8).
     *
     * @since 8.0.0
     */
    public function match($pathinfo): array
    {
        try {
            $request = Request::create($pathinfo);
        } catch (BadRequestException $e) {
            throw new ResourceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }
        return $this->matchRequest($request);
    }

}
