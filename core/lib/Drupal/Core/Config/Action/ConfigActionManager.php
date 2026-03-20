<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action;

use Drupal\Component\Plugin\Exception\Plugin_Not_Found_Exception;
use Drupal\Component\Plugin\Plugin_Base;
use Drupal\Core\Cache\Cache_Backend_Interface;
use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Config_Factory_Interface;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\Storage_Interface;
use Drupal\Core\Config\Typed_Config_Manager_Interface;
use Drupal\Core\Extension\Module_Handler_Interface;
use Drupal\Core\Plugin\Default_Plugin_Manager;
use Drupal\Core\Recipe\Invalid_Config_Exception;
use Drupal\Core\Validation\Plugin\Validation\Constraint\Fully_Validatable_Constraint;
/**
 * @defgroup config_action_api Config Action API
 * @{
 * Information about the classes and interfaces that make up the Config Action
 * API.
 *
 * Configuration actions are plugins that manipulate simple configuration or
 * configuration entities. The configuration action plugin manager can apply
 * configuration actions. For example, the API is leveraged by recipes to create
 * roles if they do not exist already and grant permissions to those roles.
 *
 * To define a configuration action in a module you need to:
 * - Define a Config Action plugin by creating a new class that implements the
 *   \Drupal\Core\Config\Action\ConfigActionPluginInterface, in namespace
 *   Plugin\ConfigAction under your module namespace. For more information about
 *   creating plugins, see the @link plugin_api Plugin API topic. @endlink
 * - Config action plugins use the attributes defined by
 *  \Drupal\Core\Config\Action\Attribute\ConfigAction. See the
 *   @link attribute Attributes topic @endlink for more information about
 *   attributes.
 *
 * Further information and examples:
 * - \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityMethod derives
 *   configuration actions from config entity methods which have the
 *   \Drupal\Core\Config\Action\Attribute\ActionMethod attribute.
 * - \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityCreate allows you to
 *   create configuration entities if they do not exist.
 * - \Drupal\Core\Config\Action\Plugin\ConfigAction\SimpleConfigUpdate allows
 *   you to update simple configuration using a config action.
 * @}
 *
 * @internal
 *   This API is experimental.
 */
class Config_Action_Manager extends Default_Plugin_Manager
{
    /**
     * Information about all deprecated plugin IDs.
     *
     * @var string[]
     */
    private static array $deprecated_plugin_ids = [];
    /**
     * Constructs a new \Drupal\Core\Config\Action\ConfigActionManager object.
     *
     * @param \Traversable $namespaces
     *   An object that implements \Traversable which contains the root paths
     *   keyed by the corresponding namespace to look for plugin implementations.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   Cache backend instance to use.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler to invoke the alter hook with.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The config manager.
     * @param \Drupal\Core\Config\StorageInterface $configStorage
     *   The active config storage.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfig
     *   The typed configuration manager service.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory service.
     */
    public function __construct(\Traversable $namespaces, Cache_Backend_Interface $cache_backend, Module_Handler_Interface $module_handler, protected readonly Config_Manager_Interface $config_manager, protected readonly Storage_Interface $config_storage, protected readonly Typed_Config_Manager_Interface $typed_config, protected readonly Config_Factory_Interface $config_factory)
    {
        assert($namespaces instanceof \ArrayAccess, '$namespaces can be accessed like an array');
        // Enable this namespace to be searched for plugins.
        $namespaces[__NAMESPACE__] = 'core/lib/Drupal/Core/Config/Action';
        parent::__construct('Plugin/ConfigAction', $namespaces, $module_handler, Config_Action_Plugin_Interface::class, Config_Action::class);
        $this->alter_info('config_action');
        $this->set_cache_backend($cache_backend, 'config_action');
    }
    /**
     * Applies a config action.
     *
     * @param string $action_id
     *   The ID of the action to apply. This can be a complete configuration
     *   action plugin ID or a shorthand action ID that is available for the
     *   entity type of the provided configuration name.
     * @param string $configName
     *   The configuration name. This may be the full name of a config object, or
     *   it may contain wildcards (to target all config entities of a specific
     *   type, or a subset thereof). See
     *   ConfigActionManager::getConfigNamesMatchingExpression() for more detail.
     * @param mixed $data
     *   The data for the action.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     *   Thrown when the config action cannot be found.
     * @throws \Drupal\Core\Config\Action\ConfigActionException
     *   Thrown when the config action fails to apply.
     *
     * @see \Drupal\Core\Config\Action\ConfigActionManager::getConfigNamesMatchingExpression()
     */
    public function apply_action(string $action_id, string $config_name, mixed $data): void
    {
        if (str_starts_with($config_name, '?')) {
            if (str_contains($config_name, '*')) {
                throw new Config_Action_Exception("The '{$config_name}' configuration name is optional because it starts with a question mark, and therefore cannot contain wildcards.");
            }
            $config_name = trim($config_name, '?');
            if (!$this->config_storage->exists($config_name)) {
                // The config action is optional and the config doesn't exist.
                // So there is nothing to do.
                return;
            }
        }
        if (!$this->has_definition($action_id)) {
            // Get the full plugin ID from the shorthand map, if it is available.
            $entity_type = $this->config_manager->get_entity_type_id_by_name($config_name);
            if ($entity_type) {
                $action_id = $this->get_shorthand_action_ids_for_entity_type($entity_type)[$action_id] ?? $action_id;
            }
        }
        try {
            /** @var \Drupal\Core\Config\Action\ConfigActionPluginInterface $action */
            $action = $this->create_instance($action_id);
        } catch (Plugin_Not_Found_Exception $e) {
            $entity_type = $this->config_manager->get_entity_type_id_by_name($config_name);
            if ($entity_type) {
                $action_ids = $this->get_shorthand_action_ids_for_entity_type($entity_type);
                $valid_ids = implode(', ', array_keys($action_ids));
                throw new Plugin_Not_Found_Exception($action_id, sprintf('The "%s" entity does not support the "%s" config action. Valid config actions for %s are: %s', $entity_type, $action_id, $entity_type, $valid_ids));
            }
            throw $e;
        }
        foreach ($this->get_config_names_matching_expression($config_name) as $name) {
            $action->apply($name, $data);
            $typed_config = $this->typed_config->create_from_name_and_data($name, $this->config_factory->get($name)->get_raw_data());
            // All config objects are mappings.
            assert($typed_config instanceof Mapping);
            foreach ($typed_config->get_constraints() as $constraint) {
                // Only validate the config if it has explicitly been marked as being
                // validatable.
                if ($constraint instanceof Fully_Validatable_Constraint) {
                    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
                    $violations = $typed_config->validate();
                    if (count($violations) > 0) {
                        throw new Invalid_Config_Exception($violations, $typed_config);
                    }
                    break;
                }
            }
        }
    }
    /**
     * Gets the names of all active config objects that match an expression.
     *
     * @param string $expression
     *   The expression to match. This may be the full name of a config object,
     *   or it may contain wildcards (to target all config entities of a specific
     *   type, or a subset thereof). For example:
     *   - `user.role.*` would target all user roles.
     *   - `user.role.anonymous` would target only the anonymous user role.
     *   - `core.entity_view_display.node.*.default` would target the default
     *     view display of every content type.
     *   - `core.entity_form_display.*.*.default` would target the default form
     *     display of every bundle of every entity type.
     *   The expression MUST begin with the prefix of a config entity type --
     *   for example, `field.field.` in the case of fields, or `user.role.` for
     *   user roles. The prefix cannot contain wildcards.
     *
     * @return string[]
     *   The names of all active config objects that match the expression.
     *
     * @throws \Drupal\Core\Config\Action\ConfigActionException
     *   Thrown if the expression does not match any known config entity type's
     *   prefix, or if the expression cannot be parsed.
     */
    private function get_config_names_matching_expression(string $expression): array
    {
        // If there are no wildcards, we can return the config name as-is.
        if (!str_contains($expression, '.*')) {
            return [$expression];
        }
        $entity_type = $this->config_manager->get_entity_type_id_by_name($expression);
        if (empty($entity_type)) {
            throw new Config_Action_Exception("No installed config entity type uses the prefix in the expression '{$expression}'. Either there is a typo in the expression or this recipe should install an additional module or depend on another recipe.");
        }
        /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
        $entity_type = $this->config_manager->get_entity_type_manager()->get_definition($entity_type);
        $prefix = $entity_type->get_config_prefix();
        // Convert the expression to a regular expression. We assume that * should
        // match the characters allowed by
        // \Drupal\Core\Config\ConfigBase::validateName(), which is permissive.
        $expression = str_replace('\*', '[^.:?*<>"\'\/\\\\]+', preg_quote($expression));
        $matches = @preg_grep("/^{$expression}\$/", $this->config_storage->list_all("{$prefix}."));
        if ($matches === false) {
            throw new Config_Action_Exception("The expression '{$expression}' could not be parsed.");
        }
        return $matches;
    }
    /**
     * Gets a map of shorthand action IDs to plugin IDs for an entity type.
     *
     * @param string $entityType
     *   The entity type ID to get the map for.
     *
     * @return string[]
     *   An array of plugin IDs keyed by shorthand action ID for the provided
     *   entity type.
     */
    protected function get_shorthand_action_ids_for_entity_type(string $entity_type): array
    {
        $map = [];
        foreach ($this->get_definitions() as $plugin_id => $definition) {
            if (in_array($entity_type, $definition['entity_types'], true) || in_array('*', $definition['entity_types'], true)) {
                $regex = '/' . Plugin_Base::DERIVATIVE_SEPARATOR . '([^' . Plugin_Base::DERIVATIVE_SEPARATOR . ']*)$/';
                $action_id = preg_match($regex, (string) $plugin_id, $matches) ? $matches[1] : $plugin_id;
                if (isset($map[$action_id])) {
                    throw new Duplicate_Config_Action_Id_Exception(sprintf('The plugins \'%s\' and \'%s\' both resolve to the same shorthand action ID for the \'%s\' entity type', $plugin_id, $map[$action_id], $entity_type));
                }
                $map[$action_id] = $plugin_id;
            }
        }
        return $map;
    }
    /**
     * {@inheritdoc}
     */
    public function alter_definitions(&$definitions): void
    {
        // Adds backwards compatibility for plugins that have been renamed.
        foreach (self::$deprecated_plugin_ids as $legacy => $new_plugin_id) {
            $definitions[$legacy] = $definitions[$new_plugin_id['replacement']];
        }
        parent::alter_definitions($definitions);
    }
    /**
     * {@inheritdoc}
     */
    public function create_instance($plugin_id, array $configuration = [])
    {
        $instance = parent::create_instance($plugin_id, $configuration);
        // Trigger deprecation notices for renamed plugins.
        if (array_key_exists($plugin_id, self::$deprecated_plugin_ids)) {
            // phpcs:ignore Drupal.Semantics.FunctionTriggerError
            @trigger_error(self::$deprecated_plugin_ids[$plugin_id]['message'], E_USER_DEPRECATED);
        }
        return $instance;
    }
}