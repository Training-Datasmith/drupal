<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Asset\Exception\Invalid_Libraries_Extend_Specification_Exception;
use Drupal\Core\Asset\Exception\Invalid_Libraries_Override_Specification_Exception;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Cache\Cache_Collector;
use Drupal\Core\Lock\Lock_Backend_Interface;
/**
 * A CacheCollector implementation for building library extension info.
 */
class Library_Discovery_Collector extends Cache_Collector implements Library_Discovery_Interface
{
    /**
     * Constructs a CacheCollector object.
     *
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache
     *   The cache backend.
     * @param \Drupal\Core\Lock\LockBackendInterface $lock
     *   The lock backend.
     * @param \Drupal\Core\Asset\LibraryDiscoveryParser $discoveryParser
     *   The library discovery parser.
     * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
     *   The theme manager.
     */
    public function __construct(Cache_Backend_Interface $cache, Lock_Backend_Interface $lock, protected \Drupal\Core\Asset\Library_Discovery_Parser $discovery_parser, protected \Drupal\Core\Theme\Theme_Manager_Interface $theme_manager)
    {
        parent::__construct(null, $cache, $lock, ['library_info']);
    }
    /**
     * {@inheritdoc}
     */
    protected function get_cid()
    {
        if (!isset($this->cid)) {
            $this->cid = 'library_info:' . $this->theme_manager->get_active_theme()->get_name();
        }
        return $this->cid;
    }
    /**
     * {@inheritdoc}
     */
    protected function resolve_cache_miss($key)
    {
        $this->storage[$key] = $this->get_library_definitions($key);
        $this->persist($key);
        return $this->storage[$key];
    }
    /**
     * Returns the library definitions for a given extension.
     *
     * This also implements libraries-overrides for entire libraries that have
     * been specified by the LibraryDiscoveryParser.
     *
     * @param string $extension
     *   The name of the extension for which library definitions will be returned.
     *
     * @return array
     *   The library definitions for $extension with overrides applied.
     *
     * @throws \Drupal\Core\Asset\Exception\InvalidLibrariesOverrideSpecificationException
     */
    protected function get_library_definitions($extension)
    {
        $libraries = $this->discovery_parser->build_by_extension($extension);
        foreach ($libraries as $name => $definition) {
            // Handle libraries that are marked for override or removal.
            // @see \Drupal\Core\Asset\LibraryDiscoveryParser::applyLibrariesOverride()
            if (isset($definition['override'])) {
                if ($definition['override'] === false) {
                    // Remove the library definition if FALSE is given.
                    unset($libraries[$name]);
                } else {
                    // Otherwise replace with existing library definition if it exists.
                    // Throw an exception if it doesn't.
                    [$replacement_extension, $replacement_name] = explode('/', (string) $definition['override']);
                    $replacement_definition = $this->get($replacement_extension);
                    if (isset($replacement_definition[$replacement_name])) {
                        $libraries[$name] = $replacement_definition[$replacement_name];
                    } else {
                        throw new Invalid_Libraries_Override_Specification_Exception(sprintf('The specified library %s does not exist.', $definition['override']));
                    }
                }
            } else {
                // If libraries are not overridden, then apply libraries-extend.
                $libraries[$name] = $this->apply_libraries_extend($extension, $name, $definition);
            }
        }
        return $libraries;
    }
    /**
     * Applies the libraries-extend specified by the active theme.
     *
     * This extends the library definitions with the those specified by the
     * libraries-extend specifications for the active theme.
     *
     * @param string $extension
     *   The name of the extension for which library definitions will be extended.
     * @param string $library_name
     *   The name of the library whose definitions is to be extended.
     * @param array $library_definition
     *   The library definition to be extended.
     *
     * @return array
     *   The library definition extended as specified by libraries-extend.
     *
     * @throws \Drupal\Core\Asset\Exception\InvalidLibrariesExtendSpecificationException
     */
    protected function apply_libraries_extend($extension, $library_name, $library_definition)
    {
        $libraries_extend = $this->theme_manager->get_active_theme()->get_libraries_extend();
        if (!empty($libraries_extend["{$extension}/{$library_name}"])) {
            foreach ($libraries_extend["{$extension}/{$library_name}"] as $library_extend_name) {
                if (isset($library_definition['deprecated'])) {
                    $extend_message = sprintf('Theme "%s" is extending a deprecated library.', $extension);
                    $library_deprecation = str_replace('%library_id%', "{$extension}/{$library_name}", $library_definition['deprecated']);
                    // phpcs:ignore Drupal.Semantics.FunctionTriggerError
                    @trigger_error("{$extend_message} {$library_deprecation}", E_USER_DEPRECATED);
                }
                if (!is_string($library_extend_name)) {
                    // Only string library names are allowed.
                    throw new Invalid_Libraries_Extend_Specification_Exception('The libraries-extend specification for each library must be a list of strings.');
                }
                [$new_extension, $new_library_name] = explode('/', $library_extend_name, 2);
                $new_libraries = $this->get($new_extension);
                if (isset($new_libraries[$new_library_name])) {
                    $library_definition = Nested_Array::merge_deep($library_definition, $new_libraries[$new_library_name]);
                } else {
                    throw new Invalid_Libraries_Extend_Specification_Exception(sprintf('The specified library "%s" does not exist.', $library_extend_name));
                }
            }
        }
        return $library_definition;
    }
    /**
     * {@inheritdoc}
     */
    public function get_libraries_by_extension($extension)
    {
        return $this->get($extension);
    }
    /**
     * {@inheritdoc}
     */
    public function get_library_by_name($extension, $name)
    {
        $libraries = $this->get_libraries_by_extension($extension);
        if (!isset($libraries[$name])) {
            return false;
        }
        if (isset($libraries[$name]['deprecated'])) {
            // phpcs:ignore Drupal.Semantics.FunctionTriggerError
            @trigger_error(str_replace('%library_id%', "{$extension}/{$name}", $libraries[$name]['deprecated']), E_USER_DEPRECATED);
        }
        return $libraries[$name];
    }
    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        parent::reset();
        $this->cid = null;
    }
}