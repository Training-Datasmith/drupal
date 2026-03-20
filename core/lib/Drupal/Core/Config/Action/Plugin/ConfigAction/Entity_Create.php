<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Exception;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\Config\Action\Plugin\Config_Action\Deriver\Entity_Create_Deriver;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @internal
 *   This API is experimental.
 */
#[Config_Action(id: 'entity_create', deriver: Entity_Create_Deriver::class)]
final readonly class Entity_Create implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    /**
     * Constructs a EntityCreate object.
     *
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The config manager.
     * @param \Drupal\Core\Config\Action\Exists $exists
     *   Determines behavior of action depending on entity existence.
     */
    public function __construct(protected Config_Manager_Interface $config_manager, protected Exists $exists)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        assert(is_array($plugin_definition) && is_array($plugin_definition['constructor_args']), '$plugin_definition contains the expected settings');
        return new static($container->get('config.manager'), ...$plugin_definition['constructor_args']);
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $value): void
    {
        if (!is_array($value)) {
            throw new Config_Action_Exception(sprintf('The value provided to create %s must be an array', $config_name));
        }
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity */
        $entity = $this->config_manager->load_config_entity_by_name($config_name);
        if ($this->exists->return_early($config_name, $entity)) {
            return;
        }
        $entity_type_manager = $this->config_manager->get_entity_type_manager();
        $entity_type_id = $this->config_manager->get_entity_type_id_by_name($config_name);
        if ($entity_type_id === null) {
            throw new Config_Action_Exception(sprintf('Cannot determine a config entity type from %s', $config_name));
        }
        /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
        $entity_type = $entity_type_manager->get_definition($entity_type_id);
        $id = substr($config_name, strlen($entity_type->get_config_prefix()) + 1);
        $entity_type_manager->get_storage($entity_type->id())->create($value + [$entity_type->get_key('id') => $id])->save();
    }
}