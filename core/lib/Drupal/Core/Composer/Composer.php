<?php

declare (strict_types=1);
namespace Drupal\Core\Composer;

use Composer\Script\Event;
use Composer\Semver\Constraint\Constraint;
/**
 * Provides static functions for composer script events.
 *
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class Composer
{
    /**
     * Fires the drupal-phpunit-upgrade script event if necessary.
     *
     * @param \Composer\Script\Event $event
     *   The event.
     *
     * @internal
     */
    public static function upgrade_php_unit(Event $event): void
    {
        $repository = $event->get_composer()->get_repository_manager()->get_local_repository();
        // This is, essentially, a null constraint. We only care whether the package
        // is present in the vendor directory yet, but findPackage() requires it.
        $constraint = new Constraint('>', '');
        $phpunit_package = $repository->find_package('phpunit/phpunit', $constraint);
        if (!$phpunit_package) {
            // There is nothing to do. The user is probably installing using the
            // --no-dev flag.
            return;
        }
        // If the PHP version is 8.4 or above and PHPUnit is less than version 11
        // call the drupal-phpunit-upgrade script to upgrade PHPUnit.
        if (!static::upgrade_php_unit_check($phpunit_package->get_version())) {
            $event->get_composer()->get_event_dispatcher()->dispatch_script('drupal-phpunit-upgrade');
        }
    }
    /**
     * Determines if PHPUnit needs to be upgraded.
     *
     * This method is located in this file because it is possible that it is
     * called before the autoloader is available.
     *
     * @param string $phpunit_version
     *   The PHPUnit version string.
     *
     * @return bool
     *   TRUE if the PHPUnit needs to be upgraded, FALSE if not.
     *
     * @internal
     */
    public static function upgrade_php_unit_check($phpunit_version): bool
    {
        return !(version_compare(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, '8.4') >= 0 && version_compare($phpunit_version, '11.0') < 0);
    }
}