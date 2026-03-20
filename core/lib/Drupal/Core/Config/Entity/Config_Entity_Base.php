<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Action\Attribute\Action_Method;
use Drupal\Core\Config\Config_Duplicate_Uuid_Exception;
use Drupal\Core\Config\Schema\Schema_Incomplete_Exception;
use Drupal\Core\Entity\Entity_Base;
use Drupal\Core\Entity\Entity_Storage_Interface;
use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Entity\Entity_With_Plugin_Collection_Interface;
use Drupal\Core\Entity\Synchronizable_Entity_Trait;
use Drupal\Core\Plugin\Plugin_Dependency_Trait;
use Drupal\Core\Plugin\Removable_Dependent_Plugin_Interface;
use Drupal\Core\Plugin\Removable_Dependent_Plugin_Return;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Defines a base configuration entity class.
 *
 * @ingroup entity_api
 */
#[\Allow_Dynamic_Properties]
abstract class Config_Entity_Base extends Entity_Base implements Config_Entity_Interface
{
    use Plugin_Dependency_Trait {
        addDependency as addDependencyTrait;
    }
    use Synchronizable_Entity_Trait;
    /**
     * The original ID of the configuration entity.
     *
     * The ID of a configuration entity is a unique string (machine name). When a
     * configuration entity is updated and its machine name is renamed, the
     * original ID needs to be known.
     *
     * @var string
     */
    protected $original_id;
    /**
     * The enabled/disabled status of the configuration entity.
     *
     * @var bool
     */
    protected $status = true;
    /**
     * The UUID for this entity.
     *
     * @var string
     */
    protected $uuid;
    /**
     * Whether the config is being deleted by the uninstall process.
     *
     * @var bool
     */
    private $is_uninstalling = false;
    /**
     * The language code of the entity's default language.
     *
     * Assumed to be English by default. ConfigEntityStorage will set an
     * appropriate language when creating new entities. This default applies to
     * imported default configuration where the language code is missing. Those
     * should be assumed to be English. All configuration entities support third
     * party settings, so even configuration entities that do not directly
     * store settings involving text in a human language may have such third
     * party settings attached. This means configuration entities should be in one
     * of the configured languages or the built-in English.
     *
     * @var string
     */
    protected $langcode = 'en';
    /**
     * Third party entity settings.
     *
     * An array of key/value pairs keyed by provider.
     *
     * @var array
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $third_party_settings = [];
    /**
     * Information maintained by Drupal core about configuration.
     *
     * Keys:
     * - default_config_hash: A hash calculated by the config.installer service
     *   and added during installation.
     *
     * @var array
     */
    // phpcs:ignore Drupal.Classes.PropertyDeclaration, Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $_core = [];
    /**
     * Trust supplied data and not use configuration schema on save.
     *
     * @var bool
     */
    protected $trusted_data = false;
    /**
     * {@inheritdoc}
     */
    public function __construct(array $values, $entity_type)
    {
        parent::__construct($values, $entity_type);
        // Backup the original ID, if any.
        // Configuration entity IDs are strings, and '0' is a valid ID.
        $original_id = $this->id();
        if ($original_id !== null && $original_id !== '') {
            $this->set_original_id($original_id);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_original_id()
    {
        return $this->original_id;
    }
    /**
     * {@inheritdoc}
     */
    public function set_original_id($id)
    {
        // Do not call the parent method since that would mark this entity as no
        // longer new. Unlike content entities, new configuration entities have an
        // ID.
        // @todo https://www.drupal.org/node/2478811 Document the entity life cycle
        //   and the differences between config and content.
        $this->original_id = $id;
        return $this;
    }
    /**
     * Overrides EntityBase::isNew().
     *
     * EntityInterface::enforceIsNew() is only supported for newly created
     * configuration entities but has no effect after saving, since each
     * configuration entity is unique.
     */
    public function is_new()
    {
        return !empty($this->enforce_is_new);
    }
    /**
     * {@inheritdoc}
     */
    public function get($property_name)
    {
        return $this->{$property_name} ?? null;
    }
    /**
     * {@inheritdoc}
     */
    #[Action_Method(adminLabel: new Translatable_Markup('Set a value'), pluralize: 'setMultiple')]
    public function set($property_name, $value)
    {
        if ($this instanceof Entity_With_Plugin_Collection_Interface && !$this->is_syncing()) {
            $plugin_collections = $this->get_plugin_collections();
            if (isset($plugin_collections[$property_name])) {
                // If external code updates the settings, pass it along to the plugin.
                $plugin_collections[$property_name]->set_configuration($value);
            }
        }
        $this->{$property_name} = $value;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    #[Action_Method(adminLabel: new Translatable_Markup('Enable'), pluralize: false)]
    public function enable()
    {
        return $this->set_status(true);
    }
    /**
     * {@inheritdoc}
     */
    #[Action_Method(adminLabel: new Translatable_Markup('Disable'), pluralize: false)]
    public function disable()
    {
        return $this->set_status(false);
    }
    /**
     * {@inheritdoc}
     */
    #[Action_Method(adminLabel: new Translatable_Markup('Set status'), pluralize: false)]
    public function set_status($status)
    {
        $this->status = (bool) $status;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function status()
    {
        return !empty($this->status);
    }
    /**
     * {@inheritdoc}
     */
    public function set_uninstalling($uninstalling): void
    {
        $this->is_uninstalling = $uninstalling;
    }
    /**
     * {@inheritdoc}
     */
    public function is_uninstalling()
    {
        return $this->is_uninstalling;
    }
    /**
     * {@inheritdoc}
     */
    public function create_duplicate()
    {
        $duplicate = parent::create_duplicate();
        // Prevent the new duplicate from being misinterpreted as a rename.
        $duplicate->set_original_id(null);
        return $duplicate;
    }
    /**
     * Callback for uasort() to sort configuration entities by weight and label.
     */
    public static function sort(Config_Entity_Interface $a, Config_Entity_Interface $b)
    {
        $a_weight = $a->weight ?? 0;
        $b_weight = $b->weight ?? 0;
        if ($a_weight == $b_weight) {
            $a_label = $a->label() ?? '';
            $b_label = $b->label() ?? '';
            return strnatcasecmp($a_label, $b_label);
        }
        return $a_weight <=> $b_weight;
    }
    /**
     * {@inheritdoc}
     */
    public function to_array()
    {
        $properties = [];
        /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
        $entity_type = $this->get_entity_type();
        $id_key = $entity_type->get_key('id');
        $property_names = $entity_type->get_properties_to_export($this->id());
        if (empty($property_names)) {
            throw new Schema_Incomplete_Exception(sprintf("Entity type '%s' is missing 'config_export' definition in its annotation", $entity_type->get_class()));
        }
        foreach ($property_names as $property_name => $export_name) {
            // Special handling for IDs so that computed compound IDs work.
            // @see \Drupal\Core\Entity\EntityDisplayBase::id()
            if ($property_name == $id_key) {
                $properties[$export_name] = $this->id();
            } else {
                $properties[$export_name] = $this->get($property_name);
            }
        }
        if (empty($this->third_party_settings)) {
            unset($properties['third_party_settings']);
        }
        if (empty($this->_core)) {
            unset($properties['_core']);
        }
        return $properties;
    }
    /**
     * Gets the typed config manager.
     *
     * @return \Drupal\Core\Config\TypedConfigManagerInterface
     *   The typed configuration plugin manager.
     */
    protected function get_typed_config()
    {
        return \Drupal::service('config.typed');
    }
    /**
     * {@inheritdoc}
     */
    public function pre_save(Entity_Storage_Interface $storage): void
    {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
        parent::pre_save($storage);
        if ($this instanceof Entity_With_Plugin_Collection_Interface && !$this->is_syncing()) {
            // Any changes to the plugin configuration must be saved to the entity's
            // copy as well.
            foreach ($this->get_plugin_collections() as $plugin_config_key => $plugin_collection) {
                $this->set($plugin_config_key, $plugin_collection->get_configuration());
            }
        }
        // Ensure this entity's UUID does not exist with a different ID, regardless
        // of whether it's new or updated.
        $matching_entities = $storage->get_query()->condition('uuid', $this->uuid())->execute();
        $matched_entity = reset($matching_entities);
        if (!empty($matched_entity) && $matched_entity != $this->id() && $matched_entity != $this->get_original_id()) {
            throw new Config_Duplicate_Uuid_Exception("Attempt to save a configuration entity '{$this->id()}' with UUID '{$this->uuid()}' when this UUID is already used for '{$matched_entity}'");
        }
        // If this entity is not new, load the original entity for comparison.
        if (!$this->is_new()) {
            $original = $storage->load_unchanged($this->get_original_id());
            // Ensure that the UUID cannot be changed for an existing entity.
            if ($original && $original->uuid() != $this->uuid()) {
                throw new Config_Duplicate_Uuid_Exception("Attempt to save a configuration entity '{$this->id()}' with UUID '{$this->uuid()}' when this entity already exists with UUID '{$original->uuid()}'");
            }
        }
        if (!$this->is_syncing()) {
            // Ensure the correct dependencies are present. If the configuration is
            // being written during a configuration synchronization then there is no
            // need to recalculate the dependencies.
            $this->calculate_dependencies();
            // If the data is trusted we need to ensure that the dependencies are
            // sorted as per their schema. If the save is not trusted then the
            // configuration will be sorted by StorableConfigBase.
            if ($this->trusted_data) {
                $mapping = ['config' => 0, 'content' => 1, 'module' => 2, 'theme' => 3, 'enforced' => 4];
                $dependency_sort = function ($dependencies) use ($mapping): array {
                    // Only sort the keys that exist.
                    $mapping_to_replace = array_intersect_key($mapping, $dependencies);
                    return array_replace($mapping_to_replace, $dependencies);
                };
                $this->dependencies = $dependency_sort($this->dependencies);
                if (isset($this->dependencies['enforced'])) {
                    $this->dependencies['enforced'] = $dependency_sort($this->dependencies['enforced']);
                }
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function __sleep(): array
    {
        $keys_to_unset = [];
        if ($this instanceof Entity_With_Plugin_Collection_Interface) {
            // Get the plugin collections first, so that the properties are
            // initialized in $vars and can be found later.
            $plugin_collections = $this->get_plugin_collections();
            $vars = get_object_vars($this);
            foreach ($plugin_collections as $plugin_config_key => $plugin_collection) {
                // Save any changes to the plugin configuration to the entity.
                $this->set($plugin_config_key, $plugin_collection->get_configuration());
                // If the plugin collections are stored as properties on the entity,
                // mark them to be unset.
                $keys_to_unset += array_filter($vars, fn($value) => $plugin_collection === $value);
            }
        }
        $vars = parent::__sleep();
        if (!empty($keys_to_unset)) {
            return array_diff($vars, array_keys($keys_to_unset));
        }
        return $vars;
    }
    /**
     * {@inheritdoc}
     */
    public function calculate_dependencies()
    {
        // All dependencies should be recalculated on every save apart from enforced
        // dependencies. This ensures stale dependencies are never saved.
        $this->dependencies = array_intersect_key($this->dependencies ?? [], ['enforced' => '']);
        if ($this instanceof Entity_With_Plugin_Collection_Interface) {
            // Configuration entities need to depend on the providers of any plugins
            // that they store the configuration for.
            foreach ($this->get_plugin_collections() as $plugin_collection) {
                foreach ($plugin_collection as $instance) {
                    $this->calculate_plugin_dependencies($instance);
                }
            }
        }
        // Configuration entities need to depend on the providers of any third
        // parties that they store the configuration for.
        foreach ($this->get_third_party_providers() as $provider) {
            $this->add_dependency('module', $provider);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function to_url($rel = null, array $options = [])
    {
        // Unless language was already provided, avoid setting an explicit language.
        $options += ['language' => null];
        return parent::to_url($rel, $options);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cache_tags_to_invalidate()
    {
        // Use cache tags that match the underlying config object's name.
        // @see \Drupal\Core\Config\ConfigBase::getCacheTags()
        return ['config:' . $this->get_config_dependency_name()];
    }
    /**
     * Overrides \Drupal\Core\Entity\DependencyTrait:addDependency().
     *
     * Note that this function should only be called from implementations of
     * \Drupal\Core\Config\Entity\ConfigEntityInterface::calculateDependencies(),
     * as dependencies are recalculated during every entity save.
     *
     * @see \Drupal\Core\Config\Entity\ConfigEntityDependency::hasDependency()
     */
    protected function add_dependency($type, $name)
    {
        // A config entity is always dependent on its provider. There is no need to
        // explicitly declare the dependency. An explicit dependency on Core, which
        // provides some plugins, is also not needed.
        if ($type == 'module' && ($name == $this->get_entity_type()->get_provider() || $name == 'core')) {
            return $this;
        }
        return $this->add_dependency_trait($type, $name);
    }
    /**
     * {@inheritdoc}
     */
    public function get_dependencies()
    {
        $dependencies = $this->dependencies;
        if (isset($dependencies['enforced'])) {
            // Merge the enforced dependencies into the list of dependencies.
            $enforced_dependencies = $dependencies['enforced'];
            unset($dependencies['enforced']);
            $dependencies = Nested_Array::merge_deep($dependencies, $enforced_dependencies);
        }
        return $dependencies;
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_dependency_name()
    {
        return $this->get_entity_type()->get_config_prefix() . '.' . $this->id();
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_target()
    {
        // For configuration entities, use the config ID for the config target
        // identifier. This ensures that default configuration (which does not yet
        // have UUIDs) can be provided and installed with references to the target,
        // and also makes config dependencies more readable.
        return $this->id();
    }
    /**
     * {@inheritdoc}
     */
    public function on_dependency_removal(array $dependencies)
    {
        $changed = false;
        if (!empty($this->third_party_settings)) {
            $old_count = count($this->third_party_settings);
            $this->third_party_settings = array_diff_key($this->third_party_settings, array_flip($dependencies['module']));
            $changed = $old_count != count($this->third_party_settings);
        }
        if ($this instanceof Entity_With_Plugin_Collection_Interface) {
            // Allow associated plugins to recalculate their dependencies and update
            // settings on dependency removal.
            foreach ($this->get_plugin_collections() as $plugin_collection) {
                foreach ($plugin_collection as $id => $instance) {
                    if ($instance instanceof Removable_Dependent_Plugin_Interface) {
                        $changed = match ($instance->on_collection_dependency_removal($dependencies)) {
                            Removable_Dependent_Plugin_Return::Remove => $plugin_collection->remove_instance_id($id) || true,
                            Removable_Dependent_Plugin_Return::Changed => true,
                            Removable_Dependent_Plugin_Return::Unchanged => $changed,
                        };
                    }
                }
            }
        }
        return $changed;
    }
    /**
     * {@inheritdoc}
     *
     * Override to never invalidate the entity's cache tag; the config system
     * already invalidates it.
     */
    protected function invalidate_tags_on_save($update)
    {
        Cache::invalidate_tags($this->get_list_cache_tags_to_invalidate());
    }
    /**
     * {@inheritdoc}
     *
     * Override to never invalidate the individual entities' cache tags; the
     * config system already invalidates them.
     */
    protected static function invalidate_tags_on_delete(Entity_Type_Interface $entity_type, array $entities)
    {
        $tags = $entity_type->get_list_cache_tags();
        foreach ($entities as $entity) {
            $tags = Cache::merge_tags($tags, $entity->get_list_cache_tags_to_invalidate());
        }
        Cache::invalidate_tags($tags);
    }
    /**
     * {@inheritdoc}
     */
    #[Action_Method(adminLabel: new Translatable_Markup('Set third-party setting'))]
    public function set_third_party_setting($module, $key, $value)
    {
        $this->third_party_settings[$module][$key] = $value;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_third_party_setting($module, $key, $default = null)
    {
        return $this->third_party_settings[$module][$key] ?? $default;
    }
    /**
     * {@inheritdoc}
     */
    public function get_third_party_settings($module)
    {
        return $this->third_party_settings[$module] ?? [];
    }
    /**
     * {@inheritdoc}
     */
    public function unset_third_party_setting($module, $key)
    {
        unset($this->third_party_settings[$module][$key]);
        // If the third party is no longer storing any information, completely
        // remove the array holding the settings for this module.
        if (empty($this->third_party_settings[$module])) {
            unset($this->third_party_settings[$module]);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_third_party_providers()
    {
        return array_keys($this->third_party_settings);
    }
    /**
     * {@inheritdoc}
     */
    public static function pre_delete(Entity_Storage_Interface $storage, array $entities): void
    {
        parent::pre_delete($storage, $entities);
        foreach ($entities as $entity) {
            if ($entity->is_uninstalling() || $entity->is_syncing()) {
                // During extension uninstall and configuration synchronization
                // deletions are already managed.
                break;
            }
            // Fix or remove any dependencies.
            $config_entities = static::get_config_manager()->get_config_entities_to_change_on_dependency_removal('config', [$entity->get_config_dependency_name()], false);
            /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $dependent_entity */
            foreach ($config_entities['update'] as $dependent_entity) {
                $dependent_entity->save();
            }
            foreach ($config_entities['delete'] as $dependent_entity) {
                $dependent_entity->delete();
            }
        }
    }
    /**
     * Gets the configuration manager.
     *
     * @return \Drupal\Core\Config\ConfigManager
     *   The configuration manager.
     */
    protected static function get_config_manager()
    {
        return \Drupal::service('config.manager');
    }
    /**
     * {@inheritdoc}
     */
    public function is_installable()
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function trust_data()
    {
        $this->trusted_data = true;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function has_trusted_data()
    {
        return $this->trusted_data;
    }
    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $return = parent::save();
        $this->trusted_data = false;
        return $return;
    }
}