<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Exception;
use Drupal\Core\Config\Action\Config_Action_Manager;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @internal
 *   This API is experimental.
 */
#[Config_Action(id: 'cloneAs', admin_label: new Translatable_Markup('Clone entity with a new ID'), entity_types: ['*'])]
final readonly class Entity_Clone implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    public function __construct(private Config_Manager_Interface $config_manager, private Config_Action_Manager $config_action_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        return new static($container->get(Config_Manager_Interface::class), $container->get('plugin.manager.config_action'));
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $value): void
    {
        if (!is_array($value)) {
            $value = ['id' => $value];
        }
        assert(is_string($value['id']));
        $value += ['fail_if_exists' => false];
        assert(is_bool($value['fail_if_exists']));
        // If the original doesn't exist, there's nothing to clone.
        $original = $this->config_manager->load_config_entity_by_name($config_name);
        if (empty($original)) {
            throw new Config_Action_Exception("Cannot clone '{$config_name}' because it does not exist.");
        }
        // Treat the original ID like a period-separated array of strings, and
        // replace any `%` parts in the clone's ID with the corresponding part of
        // the original ID. For example, if we're cloning an entity view display
        // with the ID `node.foo.teaser`, and the clone's ID is
        // `node.%.search_result`, the final ID of the clone will be
        // `node.foo.search_result`.
        $original_id_parts = explode('.', (string) $original->id());
        $clone_id_parts = explode('.', (string) $value['id']);
        assert(count($original_id_parts) === count($clone_id_parts));
        foreach ($clone_id_parts as $index => $part) {
            $clone_id_parts[$index] = $part === '%' ? $original_id_parts[$index] : $part;
        }
        $value['id'] = implode('.', $clone_id_parts);
        $clone = $original->create_duplicate();
        $clone->set($original->get_entity_type()->get_key('id'), $value['id']);
        $create_action = 'entity_create:' . ($value['fail_if_exists'] ? 'create' : 'createIfNotExists');
        // Use the config action manager to invoke the create action on the clone,
        // so that it will be validated.
        $this->config_action_manager->apply_action($create_action, $clone->get_config_dependency_name(), $clone->to_array());
    }
}