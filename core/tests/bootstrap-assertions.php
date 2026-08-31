<?php

/**
 * @file
 * PHPUnit bootstrap that ensures assertions are enabled for the test run.
 *
 * PHP must be started with zend.assertions=1 for assert() to throw
 * AssertionError. Re-execute the PHPUnit process when needed.
 */

$argv = $_SERVER['argv'] ?? [];
$is_phpunit_invocation = false;
foreach ($argv as $argument) {
    if (is_string($argument) && str_contains($argument, 'phpunit')) {
        $is_phpunit_invocation = true;
        break;
    }
}

if (PHP_SAPI === 'cli' && (int) ini_get('zend.assertions') < 1 && getenv('DRUPAL_PHPUNIT_ASSERTIONS_REEXEC') !== '1' && $is_phpunit_invocation) {
    putenv('DRUPAL_PHPUNIT_ASSERTIONS_REEXEC=1');
    $command = array_merge([PHP_BINARY, '-d', 'zend.assertions=1'], $_SERVER['argv']);
    $escaped = array_map(static fn (string $arg): string => escapeshellarg($arg), $command);
    passthru(implode(' ', $escaped), $exit_code);
    exit($exit_code);
}

require __DIR__ . '/bootstrap.php';
