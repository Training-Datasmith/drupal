<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Action\Entity_Method_Exception;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\Config\Action\Plugin\Config_Action\Deriver\Entity_Method_Deriver;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Makes config entity methods with the ActionMethod attribute into actions.
 *
 * For example, adding the ActionMethod attribute to
 * \Drupal\user\Entity\Role::grantPermission() allows permissions to be added to
 * roles via config actions.
 *
 * When calling \Drupal\Core\Config\Action\ConfigActionManager::applyAction()
 * the $data parameter is mapped to the method's arguments using the following
 * rules:
 * - If $data is not an array, the method must only have one argument or one
 *   required argument.
 * - If $data is an array and the method only accepts a single argument, the
 *   array will be passed to the first argument.
 * - If $data is an array and the method accepts more than one argument, $data
 *   will be unpacked into the method arguments.
 *
 * @internal
 *   This API is experimental.
 *
 * @see \Drupal\Core\Config\Action\Attribute\ActionMethod
 */
#[Config_Action(id: 'entity_method', deriver: Entity_Method_Deriver::class)]
final readonly class Entity_Method implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    /**
     * Constructs a EntityMethod object.
     *
     * @param string $pluginId
     *   The config action plugin ID.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The config manager.
     * @param string $method
     *   The method to call on the config entity.
     * @param \Drupal\Core\Config\Action\Exists $exists
     *   Determines behavior of action depending on entity existence.
     * @param int $numberOfParams
     *   The number of parameters the method has.
     * @param int $numberOfRequiredParams
     *   The number of required parameters the method has.
     * @param bool $pluralized
     *   Determines whether an array maps to multiple calls.
     */
    public function __construct(protected string $plugin_id, protected Config_Manager_Interface $config_manager, protected string $method, protected Exists $exists, protected int $number_of_params, protected int $number_of_required_params, protected bool $pluralized)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        assert(is_array($plugin_definition) && is_array($plugin_definition['constructor_args']), '$plugin_definition contains the expected settings');
        return new static($plugin_id, $container->get('config.manager'), ...$plugin_definition['constructor_args']);
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $value): void
    {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity */
        $entity = $this->config_manager->load_config_entity_by_name($config_name);
        if ($this->exists->return_early($config_name, $entity)) {
            return;
        }
        $entity = $this->pluralized ? $this->apply_pluralized($entity, $value) : $this->apply_single($entity, $value);
        $entity->save();
    }
    /**
     * Applies the action to entity treating the $values array as multiple calls.
     *
     * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
     *   The entity to apply the action to.
     * @param mixed $values
     *   The values for the action to use.
     *
     * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
     *   The unsaved entity with the action applied.
     */
    private function apply_pluralized(Config_Entity_Interface $entity, mixed $values): Config_Entity_Interface
    {
        if (!is_array($values)) {
            throw new Entity_Method_Exception(sprintf('The pluralized entity method config action \'%s\' requires an array value in order to call %s::%s() multiple times', $this->plugin_id, $entity->get_entity_type()->get_class(), $this->method));
        }
        foreach ($values as $value) {
            $entity = $this->apply_single($entity, $value);
        }
        return $entity;
    }
    /**
     * Applies the action to entity treating the $values array a single call.
     *
     * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
     *   The entity to apply the action to.
     * @param mixed $value
     *   The value for the action to use.
     *
     * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
     *   The unsaved entity with the action applied.
     */
    private function apply_single(Config_Entity_Interface $entity, mixed $value): Config_Entity_Interface
    {
        // If $value is not an array then we only support calling the method if the
        // number of parameters or required parameters is 1. If there is only 1
        // parameter and $value is an array then assume that the parameter expects
        // an array.
        if (!is_array($value) || $this->number_of_params === 1) {
            if ($this->number_of_required_params !== 1 && $this->number_of_params !== 1) {
                throw new Entity_Method_Exception(sprintf('Entity method config action \'%s\' requires an array value. The number of parameters or required parameters for %s::%s() is not 1', $this->plugin_id, $entity->get_entity_type()->get_class(), $this->method));
            }
            $result = $entity->{$this->method}($value);
        } else {
            $result = $entity->{$this->method}(...$value);
        }
        // If an instance of the entity (either itself, or a clone) was returned
        // by the method, return that.
        return is_a($result, $entity::class) ? $result : $entity;
    }
}