<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Nested_Array;
use Drupal\Component\Utility\Url_Helper;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Language\Language_Interface;
/**
 * The default asset resolver.
 */
class Asset_Resolver implements Asset_Resolver_Interface
{
    /**
     * Constructs a new AssetResolver instance.
     *
     * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
     *   The library discovery service.
     * @param \Drupal\Core\Asset\LibraryDependencyResolverInterface $libraryDependencyResolver
     *   The library dependency resolver.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
     *   The theme manager.
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache
     *   The cache backend.
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
     *   The theme handler service.
     */
    public function __construct(protected \Drupal\Core\Asset\Library_Discovery_Interface $library_discovery, protected \Drupal\Core\Asset\Library_Dependency_Resolver_Interface $library_dependency_resolver, protected \Drupal\Core\Extension\Module_Handler_Interface $module_handler, protected \Drupal\Core\Theme\Theme_Manager_Interface $theme_manager, protected \Drupal\Core\Language\Language_Manager_Interface $language_manager, protected \Drupal\Core\Cache\Cache_Backend_Interface $cache, protected \Drupal\Core\Extension\Theme_Handler_Interface $theme_handler)
    {
    }
    /**
     * Returns the libraries that need to be loaded.
     *
     * For example, with core/a depending on core/c and core/b on core/d:
     * @code
     * $assets = new AttachedAssets();
     * $assets->setLibraries(['core/a', 'core/b', 'core/c']);
     * $assets->setAlreadyLoadedLibraries(['core/c']);
     * $resolver->getLibrariesToLoad($assets, 'js') === ['core/a', 'core/b', 'core/d']
     * @endcode
     *
     * The attached assets tend to be in the order that libraries were attached
     * during a request. To minimize the number of unique aggregated asset URLs
     * and files, we normalize the list by filtering out libraries that don't
     * include the asset type being built as well as ensuring a reliable order of
     * the libraries based on their dependencies.
     *
     * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
     *   The assets attached to the current response.
     * @param string|null $asset_type
     *   The asset type to load.
     *
     * @return string[]
     *   A list of libraries and their dependencies, in the order they should be
     *   loaded, excluding any libraries that have already been loaded.
     */
    protected function get_libraries_to_load(Attached_Assets_Interface $assets, ?string $asset_type = null): array
    {
        // @see Drupal\FunctionalTests\Core\Asset\AssetOptimizationTestUmami
        // @todo https://www.drupal.org/project/drupal/issues/1945262
        $libraries_to_load = array_diff($this->library_dependency_resolver->get_libraries_with_dependencies($assets->get_libraries()), $this->library_dependency_resolver->get_libraries_with_dependencies($assets->get_already_loaded_libraries()));
        if ($asset_type) {
            $libraries_to_load = $this->filter_libraries_by_type($libraries_to_load, $asset_type);
        }
        // We now have a complete list of libraries requested. However, this list
        // could be in any order depending on when libraries were attached during
        // the page request, which can result in different file contents and URLs
        // even for an otherwise identical set of libraries. To ensure that any
        // particular set of libraries results in the same aggregate URL, sort the
        // libraries, then generate the minimum representative set again.
        sort($libraries_to_load);
        $minimum_libraries = $this->library_dependency_resolver->get_minimal_representative_subset($libraries_to_load);
        $libraries_to_load = array_diff($this->library_dependency_resolver->get_libraries_with_dependencies($minimum_libraries), $this->library_dependency_resolver->get_libraries_with_dependencies($assets->get_already_loaded_libraries()));
        // Now remove any libraries without the relevant asset type again, since
        // they have been brought back in via dependencies.
        if ($asset_type) {
            return $this->filter_libraries_by_type($libraries_to_load, $asset_type);
        }
        return $libraries_to_load;
    }
    /**
     * Filter libraries that don't contain an asset type.
     *
     * @param array $libraries
     *   An array of library definitions.
     * @param string $asset_type
     *   The type of asset, either 'js' or 'css'.
     *
     * @return array
     *   The filtered libraries array.
     */
    protected function filter_libraries_by_type(array $libraries, string $asset_type): array
    {
        foreach ($libraries as $key => $library) {
            [$extension, $name] = explode('/', (string) $library, 2);
            $definition = $this->library_discovery->get_library_by_name($extension, $name);
            if (empty($definition[$asset_type])) {
                unset($libraries[$key]);
            }
        }
        return $libraries;
    }
    /**
     * {@inheritdoc}
     */
    public function get_css_assets(Attached_Assets_Interface $assets, $optimize, ?Language_Interface $language = null)
    {
        if (!$assets->get_libraries()) {
            return [];
        }
        // Get the complete list of libraries to load including dependencies.
        $libraries_to_load = $this->get_libraries_to_load($assets, 'css');
        if (!$libraries_to_load) {
            return [];
        }
        if (!isset($language)) {
            $language = $this->language_manager->get_current_language();
        }
        // Add the active theme name to the cache key since active themes may
        // implement hook_library_info_alter().
        $active_theme = $this->theme_manager->get_active_theme()->get_name();
        // Add the default theme name to the cache key since css generated for an
        // active admin theme may include the default theme's ckeditor5-stylesheets
        // and default themes may be set conditionally and dynamically.
        $default_theme = $this->theme_handler->get_default();
        $cid = 'css:' . $active_theme . ':' . $default_theme . ':' . $language->get_id() . Crypt::hash_base64(serialize($libraries_to_load)) . (int) $optimize;
        if ($cached = $this->cache->get($cid)) {
            return $cached->data;
        }
        $css = [];
        $default_options = ['type' => 'file', 'group' => CSS_AGGREGATE_DEFAULT, 'weight' => 0, 'media' => 'all', 'preprocess' => true];
        foreach ($libraries_to_load as $library) {
            [$extension, $name] = explode('/', $library, 2);
            $definition = $this->library_discovery->get_library_by_name($extension, $name);
            foreach ($definition['css'] as $options) {
                $options += $default_options;
                // Copy the asset library license information to each file.
                $options['license'] = $definition['license'];
                // Files with a query string cannot be preprocessed.
                if ($options['type'] === 'file' && $options['preprocess'] && str_contains((string) $options['data'], '?')) {
                    $options['preprocess'] = false;
                }
                // Always add a tiny value to the weight, to conserve the insertion
                // order.
                $options['weight'] += count($css) / 30000;
                // CSS files are being keyed by the full path.
                $css[$options['data']] = $options;
            }
        }
        // Allow modules and themes to alter the CSS assets.
        $this->module_handler->alter('css', $css, $assets, $language);
        $this->theme_manager->alter('css', $css, $assets, $language);
        if (!empty($css)) {
            // Sort CSS items, so that they appear in the correct order.
            uasort($css, [static::class, 'sort']);
            if ($optimize) {
                $css = \Drupal::service('asset.css.collection_optimizer')->optimize($css, array_values($libraries_to_load), $language);
            }
        }
        $this->cache->set($cid, $css, Cache_Backend_Interface::CACHE_PERMANENT, ['library_info']);
        return $css;
    }
    /**
     * Returns the JavaScript settings assets for this response's libraries.
     *
     * Gathers all drupalSettings from all libraries in the attached assets
     * collection and merges them.
     *
     * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
     *   The assets attached to the current response.
     *
     * @return array
     *   A (possibly optimized) collection of JavaScript assets.
     */
    protected function get_js_settings_assets(Attached_Assets_Interface $assets)
    {
        $settings = [];
        foreach ($this->get_libraries_to_load($assets, 'js') as $library) {
            [$extension, $name] = explode('/', $library, 2);
            $definition = $this->library_discovery->get_library_by_name($extension, $name);
            if (isset($definition['drupalSettings'])) {
                $settings = Nested_Array::merge_deep_array([$settings, $definition['drupalSettings']], true);
            }
        }
        return $settings;
    }
    /**
     * {@inheritdoc}
     */
    public function get_js_assets(Attached_Assets_Interface $assets, $optimize, ?Language_Interface $language = null): array
    {
        $asset_settings = $assets->get_settings();
        if (!$assets->get_libraries() && !$asset_settings) {
            return [[], []];
        }
        if (!isset($language)) {
            $language = $this->language_manager->get_current_language();
        }
        $theme_info = $this->theme_manager->get_active_theme();
        // Get the complete list of libraries to load including dependencies.
        $libraries_to_load = $this->get_libraries_to_load($assets, 'js');
        // Collect all libraries that contain JS assets and are in the header.
        $header_js_libraries = [];
        foreach ($libraries_to_load as $key => $library) {
            [$extension, $name] = explode('/', $library, 2);
            $definition = $this->library_discovery->get_library_by_name($extension, $name);
            if (!empty($definition['header'])) {
                $header_js_libraries[] = $library;
            }
        }
        // If all the libraries to load contained only CSS, there is nothing further
        // to do here, so return early.
        if (!$libraries_to_load && !$asset_settings) {
            return [[], []];
        }
        // Add the theme name to the cache key since themes may implement
        // hook_library_info_alter(). Additionally add the current language to
        // support translation of JavaScript files via hook_js_alter().
        $cid = 'js:' . $theme_info->get_name() . ':' . $language->get_id() . ':' . Crypt::hash_base64(serialize($libraries_to_load)) . ':' . (int) $optimize;
        if ($cached = $this->cache->get($cid)) {
            [$js_assets_header, $js_assets_footer, $settings, $settings_in_header] = $cached->data;
        } else {
            $javascript = [];
            $default_options = ['type' => 'file', 'group' => JS_DEFAULT, 'weight' => 0, 'cache' => true, 'preprocess' => true, 'attributes' => [], 'version' => null];
            // The current list of header JS libraries are only those libraries that
            // are in the header, but their dependencies must also be loaded for them
            // to function correctly, so update the list with those.
            $header_js_libraries = $this->library_dependency_resolver->get_libraries_with_dependencies($header_js_libraries);
            foreach ($libraries_to_load as $library) {
                [$extension, $name] = explode('/', $library, 2);
                $definition = $this->library_discovery->get_library_by_name($extension, $name);
                foreach ($definition['js'] as $options) {
                    $options += $default_options;
                    // Copy the asset library license information to each file.
                    $options['license'] = $definition['license'];
                    // 'scope' is a calculated option, based on which libraries are
                    // marked to be loaded from the header (see above).
                    $options['scope'] = in_array($library, $header_js_libraries) ? 'header' : 'footer';
                    // Preprocess can only be set if caching is enabled and no
                    // attributes are set.
                    $options['preprocess'] = $options['cache'] && empty($options['attributes']) ? $options['preprocess'] : false;
                    // Always add a tiny value to the weight, to conserve the insertion
                    // order.
                    $options['weight'] += count($javascript) / 30000;
                    // Local and external files must keep their name as the associative
                    // key so the same JavaScript file is not added twice.
                    $javascript[$options['data']] = $options;
                }
            }
            // Allow modules and themes to alter the JavaScript assets.
            $this->module_handler->alter('js', $javascript, $assets, $language);
            $this->theme_manager->alter('js', $javascript, $assets, $language);
            // Sort JavaScript assets, so that they appear in the correct order.
            uasort($javascript, [static::class, 'sort']);
            // Prepare the return value: filter JavaScript assets per scope.
            $js_assets_header = [];
            $js_assets_footer = [];
            foreach ($javascript as $key => $item) {
                if ($item['scope'] == 'header') {
                    $js_assets_header[$key] = $item;
                } elseif ($item['scope'] == 'footer') {
                    $js_assets_footer[$key] = $item;
                }
            }
            if ($optimize) {
                $collection_optimizer = \Drupal::service('asset.js.collection_optimizer');
                $js_assets_header = $collection_optimizer->optimize($js_assets_header, $libraries_to_load);
                $js_assets_footer = $collection_optimizer->optimize($js_assets_footer, $libraries_to_load);
            }
            // Always build settings from js libraries. They may or may not be
            // used later depending on whether the core/drupalSettings library is
            // requested.
            $settings = $this->get_js_settings_assets($assets);
            // Allow modules to add cached JavaScript settings.
            $this->module_handler->invoke_all_with('js_settings_build', function (callable $hook, string $module) use (&$settings, $assets): void {
                $hook($settings, $assets);
            });
            $settings_in_header = in_array('core/drupalSettings', $header_js_libraries);
            $this->cache->set($cid, [$js_assets_header, $js_assets_footer, $settings, $settings_in_header], Cache_Backend_Interface::CACHE_PERMANENT, ['library_info']);
        }
        // If the core/drupalSettings library is being loaded or is already
        // loaded, get the JavaScript settings assets, and convert them into a
        // single "regular" JavaScript asset. But only if there are settings to
        // add. Do the quickest checks first.
        $process_settings = false;
        if (count($libraries_to_load) > 0 || count($asset_settings) > 0) {
            $process_settings = in_array('core/drupalSettings', $libraries_to_load) || in_array('core/drupalSettings', $this->library_dependency_resolver->get_libraries_with_dependencies($assets->get_already_loaded_libraries()));
        }
        if ($process_settings) {
            // Attached settings override both library definitions and
            // hook_js_settings_build().
            $settings = Nested_Array::merge_deep_array([$settings, $asset_settings], true);
            // Allow modules and themes to alter the JavaScript settings.
            $this->module_handler->alter('js_settings', $settings, $assets);
            $this->theme_manager->alter('js_settings', $settings, $assets);
            // Update the $assets object accordingly, so that it reflects the final
            // settings.
            $assets->set_settings($settings);
            // Convert ajaxPageState to a compressed string from an array, since it is
            // used by ajax.js to pass to AJAX requests as a query parameter.
            if (isset($settings['ajaxPageState']['libraries'])) {
                $settings['ajaxPageState']['libraries'] = Url_Helper::compress_query_parameter($settings['ajaxPageState']['libraries']);
            }
            $settings_as_inline_javascript = ['type' => 'setting', 'group' => JS_SETTING, 'weight' => 0, 'data' => $settings];
            $settings_js_asset = ['drupalSettings' => $settings_as_inline_javascript];
            // Prepend to the list of JS assets, to render it first. Preferably in
            // the footer, but in the header if necessary.
            if ($settings_in_header) {
                $js_assets_header = $settings_js_asset + $js_assets_header;
            } else {
                $js_assets_footer = $settings_js_asset + $js_assets_footer;
            }
        }
        return [$js_assets_header, $js_assets_footer];
    }
    /**
     * Sorts CSS and JavaScript resources.
     *
     * Callback for uasort().
     *
     * This sort order helps optimize front-end performance while providing
     * modules and themes with the necessary control for ordering the CSS and
     * JavaScript appearing on a page.
     *
     * @param array $a
     *   First item for comparison. The compared items should be associative
     *   arrays of member items.
     * @param array $b
     *   Second item for comparison.
     *
     * @return int
     *   The comparison result for uasort().
     */
    public static function sort(array $a, array $b): int
    {
        // First order by group, so that all items in the CSS_AGGREGATE_DEFAULT
        // group appear before items in the CSS_AGGREGATE_THEME group. Modules may
        // create additional groups by defining their own constants.
        if ($a['group'] < $b['group']) {
            return -1;
        }
        if ($a['group'] > $b['group']) {
            return 1;
        }
        // Finally, order by weight.
        if ($a['weight'] < $b['weight']) {
            return -1;
        }
        if ($a['weight'] > $b['weight']) {
            return 1;
        }
        return 0;
    }
}