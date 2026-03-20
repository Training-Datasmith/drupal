<?php

declare(strict_types=1);

/**
 * @file
 * Examples of Drupal's Routing and Access system.
 *
 * These examples show how routes are matched, how access is checked, and
 * how to generate URLs from route names using the Drupal URL API.
 *
 * Note: route matching and access checking happen automatically on every
 * request via DrupalKernel. The examples below show how to interact with
 * the routing system programmatically, which is useful in tests, migrations,
 * or custom command-line tools.
 */

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

// ---------------------------------------------------------------------------
// Example 1: Get the current route match in a controller or service
// ---------------------------------------------------------------------------

// Inject RouteMatchInterface via the service container.
/** @var RouteMatchInterface $route_match */
$route_match = \Drupal::routeMatch();

$route_name = $route_match->getRouteName();
$node = $route_match->getParameter('node');  // Upcasted entity object.

printf("Current route: %s\n", $route_name ?? '(none)');

if ($node !== null) {
    printf("Node parameter: NID %d\n", $node->id());
}

// ---------------------------------------------------------------------------
// Example 2: Generate an absolute URL from a route name
// ---------------------------------------------------------------------------

// Internal link to a node's canonical page.
$url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
printf("Node URL: %s\n", $url->toString());

// External URL with options.
$url = Url::fromRoute('entity.node.canonical', ['node' => 42], [
    'absolute' => true,
    'query'    => ['utm_source' => 'api'],
    'fragment' => 'comments',
]);
printf("Absolute URL with query: %s\n", $url->toString());

// ---------------------------------------------------------------------------
// Example 3: Check route access programmatically
// ---------------------------------------------------------------------------

/** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
$access_manager = \Drupal::service('access_manager');

/** @var \Drupal\Core\Session\AccountInterface $account */
$account = \Drupal::currentUser();

$access_result = $access_manager->checkNamedRoute(
    'entity.node.edit_form',
    ['node' => 1],
    $account
);

printf(
    "Current user can edit node 1: %s\n",
    $access_result->isAllowed() ? 'yes' : 'no'
);

// Access results are cache-aware objects.
if ($access_result instanceof \Drupal\Core\Cache\CacheableDependencyInterface) {
    printf("Cache tags: %s\n", implode(', ', $access_result->getCacheTags()));
}

// ---------------------------------------------------------------------------
// Example 4: Build a redirect response to a named route
// ---------------------------------------------------------------------------

use Drupal\Core\Routing\TrustedRedirectResponse;

// Only use TrustedRedirectResponse for internal or known-safe destinations.
$redirect = new \Symfony\Component\HttpFoundation\RedirectResponse(
    Url::fromRoute('entity.node.canonical', ['node' => 1], ['absolute' => true])->toString()
);

// In a controller, return the response directly:
// return $redirect;

// ---------------------------------------------------------------------------
// Example 5: Using the URL object for link generation in a render array
// ---------------------------------------------------------------------------

$link_render_array = [
    '#type'  => 'link',
    '#title' => t('View article'),
    '#url'   => Url::fromRoute('entity.node.canonical', ['node' => 1]),
    '#attributes' => ['class' => ['my-link']],
];

// Pass $link_render_array to a Drupal render pipeline, e.g. return it from
// a block's build() method.
