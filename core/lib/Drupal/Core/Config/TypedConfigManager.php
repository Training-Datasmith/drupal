<?php

declare (strict_types=1);
namespace Drupal\Core\Config;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Config\Schema\Config_Schema_Alter_Exception;
use Drupal\Core\Config\Schema\Config_Schema_Discovery;
use Drupal\Core\Config\Schema\Sequence_Data_Definition;
use Drupal\Core\Config\Schema\Type_Resolver;
use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Dependency_Injection\Class_Resolver_Interface;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Typed_Data\Map_Data_Definition;
use Drupal\Core\Typed_Data\Traversable_Typed_Data_Interface;
use Drupal\Core\Typed_Data\Typed_Data_Manager;
use Drupal\Core\Validation\Plugin\Validation\Constraint\Fully_Validatable_Constraint;
/**
 * Manages config schema type plugins.
 */
class Typed_Config_Manager extends Typed_Data_Manager implements Typed_Config_Manager_Interface
{
    /**
     * The array of plugin definitions, keyed by plugin id.
     *
     * @var array
     */
    protected $definitions;
    /**
     * Creates a new typed configuration manager.
     *
     * @param \Drupal\Core\Config\StorageInterface $configStorage
     *   The storage object to use for reading schema data.
     * @param \Drupal\Core\Config\StorageInterface $schemaStorage
     *   The storage object to use for reading schema data.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache
     *   The cache backend to use for caching the definitions.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler.
     * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
     *   (optional) The class resolver.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Interface $config_storage, protected \Drupal\Core\Config\Storage_Interface $schema_storage, Cache_Backend_Interface $cache, Module_Handler_Interface $module_handler, ?Class_Resolver_Interface $class_resolver = null)
    {
        $this->set_cache_backend($cache, 'typed_config_definitions');
        $this->alter_info('config_schema_info');
        $this->module_handler = $module_handler;
        $this->class_resolver = $class_resolver ?: \Drupal::service('class_resolver');
    }
    /**
     * {@inheritdoc}
     */
    protected function get_discovery()
    {
        if (!isset($this->discovery)) {
            $this->discovery = new Config_Schema_Discovery($this->schema_storage);
        }
        return $this->discovery;
    }
    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $data = $this->config_storage->read($name);
        if ($data === false) {
            // For a typed config the data MUST exist.
            throw new \InvalidArgumentException("Missing required data for typed configuration: {$name}");
        }
        return $this->create_from_name_and_data($name, $data);
    }
    /**
     * {@inheritdoc}
     */
    public function build_data_definition(array $definition, $value, $name = null, $parent = null)
    {
        // Add default values for data type and replace variables.
        $definition += ['type' => 'undefined'];
        $replace = [];
        $type = $definition['type'];
        if (strpos((string) $type, ']')) {
            // Replace variable names in definition.
            $replace = is_array($value) ? $value : [];
            if (isset($parent)) {
                $replace['%parent'] = $parent;
            }
            if (isset($name)) {
                $replace['%key'] = $name;
            }
            $type = Type_Resolver::resolve_dynamic_type_name($type, $replace);
            // Remove the type from the definition so that it is replaced with the
            // concrete type from schema definitions.
            unset($definition['type']);
        }
        // Add default values from type definition.
        $definition += $this->get_definition_with_replacements($type, $replace);
        $data_definition = $this->create_data_definition($definition['type']);
        // Pass remaining values from definition array to data definition.
        foreach ($definition as $key => $value) {
            if (!isset($data_definition[$key])) {
                $data_definition[$key] = $value;
            }
        }
        // All values are optional by default (meaning they can be NULL), except for
        // mappings and sequences. A sequence can only be NULL when `nullable: true`
        // is set on the config schema type definition. This is unintuitive and
        // contradicts Drupal core's documentation.
        // @see https://www.drupal.org/node/2264179
        // @see https://www.drupal.org/node/1978714
        // To gradually evolve configuration schemas in the Drupal ecosystem to be
        // validatable, this needs to be clarified in a non-disruptive way. Any
        // config schema type definition — that is, a top-level entry in a
        // *.schema.yml file — can opt into stricter behavior, whereby a property
        // cannot be NULL unless it specifies `nullable: true`, by adding
        // `FullyValidatable` as a top-level validation constraint.
        // @see https://www.drupal.org/node/3364108
        // @see https://www.drupal.org/node/3364109
        // @see \Drupal\Core\TypedData\TypedDataManager::getDefaultConstraints()
        if ($parent) {
            $root_type = $parent->get_root()->get_data_definition()->get_data_type();
            $root_type_has_opted_in = false;
            foreach ($parent->get_root()->get_constraints() as $constraint) {
                if ($constraint instanceof Fully_Validatable_Constraint) {
                    $root_type_has_opted_in = true;
                    break;
                }
            }
            // If this is a dynamically typed property path, then not only must the
            // (absolute) root type be considered, but also the (relative) static root
            // type: the resolved type.
            // For example, `block.block.*:settings` has a dynamic type defined:
            // `block.settings.[%parent.plugin]`, but `block.block.*:plugin` does not.
            // Consequently, the value at the `plugin` property path depends only on
            // the `block.block.*` config schema type and hence only that config
            // schema type must have the `FullyValidatable` constraint, because it
            // defines which value are required.
            // In contrast, the `block.block.*:settings` property path depends on
            // whichever dynamic type `block.settings.[%parent.plugin]` resolved to,
            // to be able to know which values are required. Therefore that resolved
            // type determines which values are required and whether it is fully
            // validatable.
            // So for example the `block.settings.system_branding_block` config schema
            // type would also need to have the `FullyValidatable` constraint to
            // consider its schema-defined keys to require values:
            // - use_site_logo
            // - use_site_name
            // - use_site_slogan
            $static_type_root = Typed_Config_Manager::get_static_type_root($parent);
            $static_type_root_type = $static_type_root->get_data_definition()->get_data_type();
            if ($root_type !== $static_type_root_type) {
                $root_type_has_opted_in = false;
                foreach ($static_type_root->get_constraints() as $c) {
                    if ($c instanceof Fully_Validatable_Constraint) {
                        $root_type_has_opted_in = true;
                        break;
                    }
                }
            }
            if ($root_type_has_opted_in) {
                $data_definition->set_required(!isset($data_definition['nullable']) || $data_definition['nullable'] === false);
            }
        }
        return $data_definition;
    }
    /**
     * Gets the static type root for a config schema object.
     *
     * @param \Drupal\Core\TypedData\TraversableTypedDataInterface $object
     *   A config schema object to get the static type root for.
     *
     * @return \Drupal\Core\TypedData\TraversableTypedDataInterface
     *   The ancestral config schema object at which the static type root lies:
     *   either the first ancestor with a dynamic type (for example:
     *   `block.block.*:settings`, which has the `block.settings.[%parent.plugin]`
     *   type) or the (absolute) root of the config object (in this example:
     *   `block.block.*`).
     */
    public static function get_static_type_root(Traversable_Typed_Data_Interface $object): Traversable_Typed_Data_Interface
    {
        $root = $object->get_root();
        $static_type_root = null;
        while ($static_type_root === null && $object !== $root) {
            // Use the parent data definition to determine the type of this mapping
            // (including the dynamic placeholders). For example:
            // - `editor.settings.[%parent.editor]`
            // - `editor.image_upload_settings.[status]`.
            $parent_data_def = $object->get_parent()->get_data_definition();
            $original_mapping_type = match (true) {
                $parent_data_def instanceof Map_Data_Definition => $parent_data_def->to_array()['mapping'][$object->get_name()]['type'],
                $parent_data_def instanceof Sequence_Data_Definition => $parent_data_def->to_array()['sequence']['type'],
                default => throw new \LogicException('Invalid config schema detected.'),
            };
            // If this mapping's type was dynamically defined, then this is the static
            // type root inside which all types are statically defined.
            if (str_contains((string) $original_mapping_type, ']')) {
                $static_type_root = $object;
                break;
            }
            $object = $object->get_parent();
        }
        // Either the discovered static type root is not the actual root, or no
        // static type root was found and it is the root config object.
        assert($static_type_root !== null && $static_type_root !== $root || $static_type_root === null && $object->get_parent() === null);
        return $static_type_root ?? $root;
    }
    /**
     * Determines the typed config type for a plugin ID.
     *
     * @param string $base_plugin_id
     *   The plugin ID.
     * @param array $definitions
     *   An array of typed config definitions.
     *
     * @return string
     *   The typed config type for the given plugin ID.
     */
    protected function determine_type($base_plugin_id, array $definitions)
    {
        if (isset($definitions[$base_plugin_id])) {
            $type = $base_plugin_id;
        } elseif (strpos($base_plugin_id, '.') && $name = $this->get_fallback_name($base_plugin_id)) {
            // Found a generic name, replacing the last element by '*'.
            $type = $name;
        } else {
            // If we don't have definition, return the 'undefined' element.
            $type = 'undefined';
        }
        return $type;
    }
    /**
     * Gets a schema definition with replacements for dynamic type names.
     *
     * @param string $base_plugin_id
     *   A plugin ID.
     * @param array $replacements
     *   An array of replacements for dynamic type names.
     * @param bool $exception_on_invalid
     *   (optional) This parameter is passed along to self::getDefinition().
     *   However, self::getDefinition() does not respect this parameter, so it is
     *   effectively useless in this context.
     *
     * @return array
     *   A schema definition array.
     */
    protected function get_definition_with_replacements($base_plugin_id, array $replacements, $exception_on_invalid = true)
    {
        $definitions = $this->get_definitions();
        $type = $this->determine_type($base_plugin_id, $definitions);
        $definition = $definitions[$type];
        // Check whether this type is an extension of another one and compile it.
        if (isset($definition['type'])) {
            $merge = $this->get_definition($definition['type'], $exception_on_invalid);
            // Preserve integer keys on merge, so sequence item types can override
            // parent settings as opposed to adding unused second, third, etc. items.
            $definition = Nested_Array::merge_deep_array([$merge, $definition], true);
            // Replace dynamic portions of the definition type.
            if (!empty($replacements) && strpos((string) $definition['type'], ']')) {
                $sub_type = $this->determine_type(Type_Resolver::resolve_dynamic_type_name($definition['type'], $replacements), $definitions);
                $sub_definition = $definitions[$sub_type];
                if (isset($definitions[$sub_type]['type'])) {
                    $sub_merge = $this->get_definition($definitions[$sub_type]['type'], $exception_on_invalid);
                    $sub_definition = Nested_Array::merge_deep_array([$sub_merge, $definitions[$sub_type]], true);
                }
                // Merge the newly determined subtype definition with the original
                // definition.
                $definition = Nested_Array::merge_deep_array([$sub_definition, $definition], true);
                $type = "{$type}||{$sub_type}";
            }
            // Unset type so we try the merge only once per type.
            unset($definition['type']);
            $this->definitions[$type] = $definition;
        }
        // Add type and default definition class.
        $definition += ['definition_class' => \Drupal\Core\Typed_Data\Data_Definition::class, 'type' => $type, 'unwrap_for_canonical_representation' => true];
        return $definition;
    }
    /**
     * {@inheritdoc}
     */
    public function get_definition($base_plugin_id, $exception_on_invalid = true)
    {
        return $this->get_definition_with_replacements($base_plugin_id, [], $exception_on_invalid);
    }
    /**
     * {@inheritdoc}
     */
    public function clear_cached_definitions(): void
    {
        $this->schema_storage->reset();
        parent::clear_cached_definitions();
    }
    /**
     * Finds fallback configuration schema name.
     *
     * @param string $name
     *   Configuration name or key.
     *
     * @return null|string
     *   The resolved schema name for the given configuration name or key. Returns
     *   null if there is no schema name to fallback to. For example,
     *   breakpoint.breakpoint.module.toolbar.narrow will check for definitions in
     *   the following order:
     *     breakpoint.breakpoint.module.toolbar.*
     *     breakpoint.breakpoint.module.*.*
     *     breakpoint.breakpoint.module.*
     *     breakpoint.breakpoint.*.*.*
     *     breakpoint.breakpoint.*
     *     breakpoint.*.*.*.*
     *     breakpoint.*
     *   Colons are also used, for example,
     *   block.settings.system_menu_block:footer will check for definitions in the
     *   following order:
     *     block.settings.system_menu_block:*
     *     block.settings.*:*
     *     block.settings.*
     *     block.*.*:*
     *     block.*
     */
    public function find_fallback(string $name): ?string
    {
        $fallback = $this->get_fallback_name($name);
        assert($fallback === null || str_ends_with($fallback, '.*'));
        return $fallback;
    }
    /**
     * Gets fallback configuration schema name.
     *
     * @param string $name
     *   Configuration name or key.
     *
     * @return null|string
     *   The resolved schema name for the given configuration name or key.
     */
    protected function get_fallback_name($name)
    {
        // Check for definition of $name with filesystem marker.
        $replaced = preg_replace('/([^\.:]+)([\.:\*]*)$/', '*\2', $name);
        if ($replaced != $name) {
            if (isset($this->definitions[$replaced])) {
                return $replaced;
            }
            // No definition for this level. Collapse multiple wildcards to a single
            // wildcard to see if there is a greedy match. For example,
            // "breakpoint.breakpoint.*.*" becomes "breakpoint.breakpoint.*".
            $one_star = preg_replace('/\.([:\.\*]*)$/', '.*', (string) $replaced);
            if ($one_star != $replaced && isset($this->definitions[$one_star])) {
                return $one_star;
            }
            // Check for next level. For example, if "breakpoint.breakpoint.*" has
            // been checked and no match found then check "breakpoint.*.*".
            return $this->get_fallback_name($replaced);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function has_config_schema($name): bool
    {
        // The schema system falls back on the Undefined class for unknown types.
        $definition = $this->get_definition($name);
        return is_array($definition) && $definition['class'] != Undefined::class;
    }
    /**
     * {@inheritdoc}
     */
    protected function alter_definitions(&$definitions)
    {
        $discovered_schema = array_keys($definitions);
        parent::alter_definitions($definitions);
        $altered_schema = array_keys($definitions);
        if ($discovered_schema != $altered_schema) {
            $added_keys = implode(',', array_diff($altered_schema, $discovered_schema));
            $removed_keys = implode(',', array_diff($discovered_schema, $altered_schema));
            if (!empty($added_keys) && !empty($removed_keys)) {
                $message = "Invoking hook_config_schema_info_alter() has added ({$added_keys}) and removed ({$removed_keys}) schema definitions";
            } elseif (!empty($added_keys)) {
                $message = "Invoking hook_config_schema_info_alter() has added ({$added_keys}) schema definitions";
            } else {
                $message = "Invoking hook_config_schema_info_alter() has removed ({$removed_keys}) schema definitions";
            }
            throw new Config_Schema_Alter_Exception($message);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function create_from_name_and_data($config_name, array $config_data)
    {
        $definition = $this->get_definition($config_name);
        $data_definition = $this->build_data_definition($definition, $config_data);
        return $this->create($data_definition, $config_data, $config_name);
    }
}