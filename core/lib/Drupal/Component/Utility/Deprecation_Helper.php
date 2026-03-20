<?php

declare (strict_types=1);
namespace Drupal\Component\Utility;

/**
 * Provides a helper method for handling deprecated code paths in projects.
 */
final class Deprecation_Helper
{
    /**
     * Helper to run a callback based on the installed version of a project.
     *
     * With this helper, contributed or custom modules can run different code
     * paths based on the version of a project (e.g. Drupal) using callbacks.
     *
     * The below templates help code editors and PHPStan understand the return
     * value of this function.
     *
     * @param string $currentVersion
     *   Version to check against.
     * @param string $deprecatedVersion
     *   Version that deprecated the old code path.
     * @param callable(): Current $currentCallable
     *   Callback for the current version.
     * @param callable(): Deprecated $deprecatedCallable
     *   Callback for deprecated code path.
     *
     * @return Current|Deprecated
     *   The method to invoke based on the current version and the version of the
     *   deprecation. The current callback when the current version is greater
     *   than or equal to the version of the deprecation. Otherwise, the
     *   deprecated callback.
     *
     * @template Current
     * @template Deprecated
     */
    public static function backwards_compatible_call(string $current_version, string $deprecated_version, callable $current_callable, callable $deprecated_callable): mixed
    {
        // Normalize the version string when it's a dev version to the first point
        // release of that minor. E.g. "10.2.x-dev" and "10.2-dev" both translate to
        // "10.2.0".
        $normalized_version = str_ends_with($current_version, '-dev') ? str_replace(['.x-dev', '-dev'], '.0', $current_version) : $current_version;
        return version_compare($normalized_version, $deprecated_version, '>=') ? $current_callable() : $deprecated_callable();
    }
}