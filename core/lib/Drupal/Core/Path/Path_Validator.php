<?php

declare(strict_types=1);

namespace Drupal\Core\Path;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Provides a default path validator and access checker.
 */
class PathValidator implements PathValidatorInterface
{
    /**
     * A router implementation which does not check access.
     *
     * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
     */
    protected $accessUnawareRouter;

    /**
     * Creates a new PathValidator.
     *
     * @param \Drupal\Core\Routing\AccessAwareRouterInterface $accessAwareRouter
     *   The access aware router.
     * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $access_unaware_router
     *   A router implementation which does not check access.
     * @param \Drupal\Core\Session\AccountInterface $account
     *   The current user.
     * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $pathProcessor
     *   The path processor.
     */
    public function __construct(protected \Drupal\Core\Routing\AccessAwareRouterInterface $accessAwareRouter, UrlMatcherInterface $access_unaware_router, protected \Drupal\Core\Session\AccountInterface $account, protected \Drupal\Core\PathProcessor\InboundPathProcessorInterface $pathProcessor)
    {
        $this->accessUnawareRouter = $access_unaware_router;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($path): bool
    {
        return (bool) $this->getUrlIfValid($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrlIfValid($path)
    {
        return $this->getUrl($path, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrlIfValidWithoutAccessCheck($path)
    {
        return $this->getUrl($path, false);
    }

    /**
     * Helper for getUrlIfValid() and getUrlIfValidWithoutAccessCheck().
     */
    protected function getUrl($path, $access_check)
    {
        $path = ltrim((string) $path, '/');

        $parsed_url = UrlHelper::parse($path);

        $options = [];
        if (!empty($parsed_url['query'])) {
            $options['query'] = $parsed_url['query'];
        }
        if (!empty($parsed_url['fragment'])) {
            $options['fragment'] = $parsed_url['fragment'];
        }
        if ($parsed_url['path'] == '<front>') {
            return new Url('<front>', [], $options);
        }
        if ($parsed_url['path'] == '<none>') {
            return new Url('<none>', [], $options);
        }

        if (UrlHelper::isExternal($path) && UrlHelper::isValid($path)) {
            if (empty($parsed_url['path'])) {
                return false;
            }
            return Url::fromUri($path);
        }

        try {
            $request = Request::create('/' . $path);
        } catch (BadRequestException) {
            return false;
        }
        $attributes = $this->getPathAttributes($path, $request, $access_check);

        if (!$attributes) {
            return false;
        }

        $route_name = $attributes[RouteObjectInterface::ROUTE_NAME];
        $route_parameters = $attributes['_raw_variables']->all();

        return new Url($route_name, $route_parameters, $options + ['query' => $request->query->all()]);
    }

    /**
     * Gets the matched attributes for a given path.
     *
     * @param string $path
     *   The path to check.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   A request object with the given path.
     * @param bool $access_check
     *   If FALSE then skip access check and check only whether the path is
     *   valid.
     *
     * @return array|false
     *   An array of request attributes or FALSE if an exception was thrown.
     */
    protected function getPathAttributes($path, Request $request, $access_check)
    {
        if (!$access_check || $this->account->hasPermission('link to any page')) {
            $router = $this->accessUnawareRouter;
        } else {
            $router = $this->accessAwareRouter;
        }

        $initial_request_context = $router->getContext() ?: new RequestContext();
        $path = $this->pathProcessor->processInbound('/' . $path, $request);

        try {
            $router->setContext((new RequestContext())->fromRequest($request));
            $result = $router->match($path);
        } catch (ResourceNotFoundException|ParamNotConvertedException|AccessDeniedHttpException|MethodNotAllowedException|BadRequestException) {
            $result = false;
        }

        $router->setContext($initial_request_context);
        return $result;
    }

}
