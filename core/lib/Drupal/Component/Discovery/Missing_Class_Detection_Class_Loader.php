<?php

declare (strict_types=1);
namespace Drupal\Component\Discovery;

/**
 * Defines a classloader that detects missing classes.
 *
 * This does not load classes. It allows calling code to explicitly check
 * whether a class that was requested failed to be discovered by other class
 * loaders.
 *
 * It also works around a PHP limitation when it attempts to load a class that
 * relies on a trait that does not exist. This is a common situation with Drupal
 * plugins, which may be intended to be dormant unless certain other modules are
 * installed.
 *
 * @see https://github.com/php/php-src/issues/17959
 * @internal
 */
final class Missing_Class_Detection_Class_Loader
{
    /**
     * An array of detected missing traits.
     */
    protected array $missing_traits = [];
    /**
     * Flag indicating whether there was an attempt to load a missing class.
     */
    protected bool $missing_class = false;
    /**
     * Records missing classes and aliases missing traits.
     *
     * This method is registered as a class loader during attribute discovery and
     * runs last. Any call to this method means that the requested class is
     * missing. If that class is a trait, it is aliased to a stub trait to avoid
     * an uncaught PHP fatal error.
     *
     * @param string $class
     *   The class name to load.
     */
    public function load_class(string $class): void
    {
        $this->missing_class = true;
        if (str_ends_with($class, 'Trait')) {
            $this->missing_traits[] = $class;
            class_alias(Stub_Trait::class, $class);
        }
    }
    /**
     * Returns whether there was an attempt to load a missing class.
     *
     * @return bool
     *   TRUE if there was an attempt to load a missing class, otherwise FALSE.
     */
    public function has_missing_class(): bool
    {
        return $this->missing_class;
    }
    /**
     * Returns all recorded missing traits since the last reset.
     *
     * @return string[]
     *   An array of traits recorded as missing.
     */
    public function get_missing_traits(): array
    {
        return $this->missing_traits;
    }
    /**
     * Resets class variables.
     */
    public function reset(): void
    {
        $this->missing_class = false;
        $this->missing_traits = [];
    }
}