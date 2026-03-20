<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Discovery;

use Drupal\Component\Discovery\Missing_Class_Detection_Class_Loader;
use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\File_Cache\File_Cache_Interface;
use Drupal\Component\Plugin\Attribute\Attribute_Interface;
use Drupal\Component\Plugin\Attribute\Plugin;
/**
 * Defines a discovery mechanism to find plugins with attributes.
 */
class Attribute_Class_Discovery implements Discovery_Interface
{
    use Discovery_Trait;
    /**
     * The file cache object.
     */
    protected File_Cache_Interface $file_cache;
    /**
     * An array of classes to skip.
     *
     * This must be static because once a class has been autoloaded by PHP, it
     * cannot be unregistered again.
     */
    protected static array $skip_classes = [];
    /**
     * List of root namespaces abbreviated to two levels.
     *
     * This list of namespaces is derived from the namespaces to look for plugin
     * implementations in, with each namespace in the list reduced to the first
     * two levels only, such as "Drupal\Component". Checking class namespaces
     * against this list provides a way to check that dependencies' classes exist
     * without using the "*_exists()" functions, which loads every class into
     * memory and can throw errors.
     *
     * @var list<string>
     */
    protected readonly array $root_two_level_namespaces;
    /**
     * Constructs a new instance.
     *
     * @param array<string, list<string>> $pluginNamespaces
     *   (optional) An array of namespace that may contain plugin implementations.
     *   Defaults to an empty array.
     * @param string $pluginDefinitionAttributeName
     *   (optional) The name of the attribute that contains the plugin definition.
     *   Defaults to 'Drupal\Component\Plugin\Attribute\Plugin'.
     */
    public function __construct(protected readonly array $plugin_namespaces = [], protected readonly string $plugin_definition_attribute_name = Plugin::class)
    {
        $file_cache_suffix = str_replace('\\', '_', $this->plugin_definition_attribute_name);
        $this->file_cache = File_Cache_Factory::get('attribute_discovery:' . $this->get_file_cache_suffix($file_cache_suffix));
        $this->root_two_level_namespaces = array_unique(array_map($this->get_two_level_namespace(...), array_keys($this->get_plugin_namespaces())));
    }
    /**
     * Gets the file cache suffix.
     *
     * This method allows classes that extend this class to add additional
     * information to the file cache collection name.
     *
     * @param string $default_suffix
     *   The default file cache suffix.
     *
     * @return string
     *   The file cache suffix.
     */
    protected function get_file_cache_suffix(string $default_suffix): string
    {
        return $default_suffix;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_definitions(): array
    {
        $definitions = [];
        $autoloader = new Missing_Class_Detection_Class_Loader();
        spl_autoload_register($autoloader->load_class(...));
        // Search for classes within all PSR-4 namespace locations.
        foreach ($this->get_plugin_namespaces() as $namespace => $dirs) {
            foreach ($dirs as $dir) {
                if (file_exists($dir)) {
                    $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($dir, \Recursive_Directory_Iterator::SKIP_DOTS));
                    foreach ($iterator as $fileinfo) {
                        assert($fileinfo instanceof \Spl_File_Info);
                        if ($fileinfo->get_extension() === 'php') {
                            if ($cached = $this->file_cache->get($fileinfo->get_path_name())) {
                                if (isset($cached['id'])) {
                                    // Explicitly unserialize this to create a new object
                                    // instance.
                                    $dependencies = isset($cached['dependencies']) ? unserialize($cached['dependencies']) : [];
                                    if (!$this->has_missing_dependencies($dependencies ?? [])) {
                                        $definitions[$cached['id']] = unserialize($cached['content']);
                                    }
                                }
                                continue;
                            }
                            $sub_path = $iterator->get_sub_iterator()->get_sub_path();
                            $sub_path = $sub_path ? str_replace(DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
                            $class = $namespace . '\\' . $sub_path . $fileinfo->get_basename('.php');
                            // Plugins may rely on Attribute classes defined by modules that
                            // are not installed. In such a case, a 'class not found' error
                            // may be thrown from reflection. However, this is an unavoidable
                            // situation with optional dependencies and plugins. Therefore,
                            // silently skip over this class and avoid writing to the cache,
                            // so that it is scanned each time. This ensures that the plugin
                            // definition will be found if the module it requires is
                            // enabled.
                            // PHP handles missing traits as an unrecoverable error.
                            // Register a special classloader that prevents a missing
                            // trait from causing an error. When it encounters a missing
                            // trait it stores that it was unable to find the trait.
                            // Because the classloader will result in the class being
                            // autoloaded we store an array of classes to skip if this
                            // method is called again.
                            // If discovery runs twice in a single request, first without
                            // the module that defines the missing trait, and second after it
                            // has been installed, we want the plugin to be discovered in the
                            // second case. Therefore, if a module has been added to skipped
                            // classes, check if the trait's namespace is available.
                            // If it is available, allow discovery.
                            // @todo a fix for this has been committed to PHP. Once that is
                            // available, attempt to make the class loader registration
                            // conditional on PHP version, then remove the logic entirely once
                            // Drupal requires PHP 8.5.
                            // @see https://github.com/php/php-src/issues/17959
                            // @see https://github.com/php/php-src/commit/8731c95b35f6838bacd12a07c50886e020aad5a6
                            if (array_key_exists($class, self::$skip_classes)) {
                                $missing_classes = self::$skip_classes[$class];
                                foreach ($missing_classes as $missing_class) {
                                    $missing_class_namespace = $this->get_two_level_namespace($missing_class);
                                    // If we arrive here a second time, and the namespace is still
                                    // unavailable, ensure discovery is skipped. Without this
                                    // explicit check for already checked classes, an invalid
                                    // class would be discovered, because once we've detected a
                                    // a missing trait and aliased the stub instead, this can't
                                    // happen again, so the class appears valid. However, if the
                                    // namespace has become available in the meantime, assume that
                                    // the class actually should be discovered since this probably
                                    // means the optional module it depends on has been enabled.
                                    if (!in_array($missing_class_namespace, $this->root_two_level_namespaces)) {
                                        $autoloader->reset();
                                        continue 2;
                                    }
                                }
                            }
                            try {
                                $class_exists = class_exists($class, true);
                                if (!$class_exists || \count($autoloader->get_missing_traits()) > 0) {
                                    // @todo remove this workaround once PHP treats missing traits
                                    // as catchable fatal errors.
                                    if (\count($autoloader->get_missing_traits()) > 0) {
                                        self::$skip_classes[$class] = $autoloader->get_missing_traits();
                                    }
                                    $autoloader->reset();
                                    continue;
                                }
                            } catch (\Error $e) {
                                if (!$autoloader->has_missing_class()) {
                                    // @todo Add test coverage for unexpected Error exceptions in
                                    // https://www.drupal.org/project/drupal/issues/3520811.
                                    $autoloader->reset();
                                    spl_autoload_unregister($autoloader->load_class(...));
                                    throw $e;
                                }
                                $autoloader->reset();
                                continue;
                            }
                            $result = $this->parse_class($class, $fileinfo);
                            ['id' => $id, 'content' => $content] = $result;
                            if ($id) {
                                if (!$this->has_missing_dependencies($result['dependencies'] ?? [])) {
                                    $definitions[$id] = $content;
                                }
                                // Explicitly serialize this to create a new object instance.
                                if (!isset(self::$skip_classes[$class])) {
                                    $this->file_cache->set($fileinfo->get_path_name(), ['id' => $id, 'content' => serialize($content), 'dependencies' => serialize($result['dependencies'] ?? null)]);
                                }
                            } else {
                                // Store a NULL object, so that the file is not parsed again.
                                $this->file_cache->set($fileinfo->get_path_name(), [null]);
                            }
                        }
                    }
                }
            }
        }
        spl_autoload_unregister($autoloader->load_class(...));
        return $definitions;
    }
    /**
     * Parses attributes from a class.
     *
     * @param class-string $class
     *   The class to parse.
     * @param \SplFileInfo $fileinfo
     *   The SPL file information for the class.
     *
     * @return array
     *   An array with the keys 'id', 'content', and 'dependencies'. The 'id' is
     *   the plugin ID, 'content' is the plugin definition, and 'dependencies' is
     *   a list of class, interface or trait names in the plugin class hierarchy.
     *
     * @throws \ReflectionException
     * @throws \Error
     */
    protected function parse_class(string $class, \Spl_File_Info $fileinfo): array
    {
        // @todo Consider performance improvements over using reflection.
        // @see https://www.drupal.org/project/drupal/issues/3395260.
        $reflection_class = new \ReflectionClass($class);
        $id = $content = null;
        if ($attributes = $reflection_class->get_attributes($this->plugin_definition_attribute_name, \Reflection_Attribute::IS_INSTANCEOF)) {
            /** @var \Drupal\Component\Plugin\Attribute\AttributeInterface $attribute */
            $attribute = $attributes[0]->new_instance();
            $this->prepare_attribute_definition($attribute, $class);
            if ($dependencies = $this->get_class_dependencies($reflection_class)) {
                // Include the dependencies in the plugin definition content in case
                // plugins need to know about them.
                $attribute->set_dependencies($dependencies);
            }
            $id = $attribute->get_id();
            $content = $attribute->get();
        }
        return ['id' => $id, 'content' => $content, 'dependencies' => $dependencies ?? null];
    }
    /**
     * Prepares the attribute definition.
     *
     * @param \Drupal\Component\Plugin\Attribute\AttributeInterface $attribute
     *   The attribute derived from the plugin.
     * @param string $class
     *   The class used for the plugin.
     */
    protected function prepare_attribute_definition(Attribute_Interface $attribute, string $class): void
    {
        $attribute->set_class($class);
    }
    /**
     * Gets an array of PSR-4 namespaces to search for plugin classes.
     *
     * @return string[][]
     *   An array of namespaces to search.
     */
    protected function get_plugin_namespaces(): array
    {
        return $this->plugin_namespaces;
    }
    /**
     * Gets a string containing the first two levels of a class name or namespace.
     *
     * @param string $namespace
     *   The class name or namespace.
     *
     * @return string
     *   A namespace string containing only two levels.
     */
    protected function get_two_level_namespace(string $namespace): string
    {
        return implode('\\', array_slice(explode('\\', $namespace), 0, 2));
    }
    /**
     * Gets the list of class, interface, and trait dependencies for the class.
     *
     * @param \ReflectionClass $reflection_class
     *   Plugin class reflection object.
     *
     * @return array{"class"?: list<class-string>, "interface"?: list<class-string>, "trait"?: list<class-string>, "provider"?: list<string>}|null
     *   The list of dependencies, keyed by type. If the type is 'class', 'trait',
     *   or 'interface', the values for the type are class names. If the type is
     *   'provider', the values for the type are provider names. NULL if there are
     *   no dependencies.
     */
    protected function get_class_dependencies(\ReflectionClass $reflection_class): ?array
    {
        $dependencies = [];
        if ($interfaces = $reflection_class->get_interface_names()) {
            $dependencies['interface'] = $interfaces;
        }
        if ($traits = $reflection_class->get_trait_names()) {
            $dependencies['trait'] = $traits;
        }
        $child_class = $reflection_class;
        while ($parent_class = $child_class->get_parent_class()) {
            $dependencies['class'][] = $parent_class->get_name();
            if ($traits = $parent_class->get_trait_names()) {
                $dependencies['trait'] ??= [];
                $dependencies['trait'] = array_unique(array_merge($dependencies['trait'], $traits));
            }
            $child_class = $parent_class;
        }
        return $dependencies ?: null;
    }
    /**
     * Whether the plugin definition has missing dependencies.
     *
     * @param array<string, array<class-string>> $dependencies
     *   An array of dependencies' class names or namespaces, keyed by type.
     *
     * @return bool
     *   TRUE if any dependencies are missing. FALSE otherwise.
     */
    protected function has_missing_dependencies(array $dependencies): bool
    {
        foreach ($dependencies as $type_dependencies) {
            foreach ($type_dependencies as $dependency) {
                $namespace = $this->get_two_level_namespace($dependency);
                if (!str_starts_with($namespace, 'Drupal')) {
                    // Not checking non-Drupal dependencies.
                    continue;
                }
                if (!in_array($namespace, $this->root_two_level_namespaces)) {
                    return true;
                }
            }
        }
        return false;
    }
}