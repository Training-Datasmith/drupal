<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Exception;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @internal
 *   This API is experimental.
 */
#[Config_Action(id: 'setProperties', admin_label: new Translatable_Markup('Set property of a config entity'), entity_types: ['*'])]
final readonly class Set_Properties implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    public function __construct(private Config_Manager_Interface $config_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        return new static($container->get(Config_Manager_Interface::class));
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $values): void
    {
        $entity = $this->config_manager->load_config_entity_by_name($config_name);
        assert($entity instanceof Config_Entity_Interface);
        assert(is_array($values));
        assert(!array_is_list($values));
        // Don't allow the ID or UUID to be changed.
        $entity_keys = $entity->get_entity_type()->get_keys();
        $forbidden_keys = array_filter([$entity_keys['id'], $entity_keys['uuid']]);
        foreach ($values as $property_name => $value) {
            if (in_array($property_name, $forbidden_keys, true)) {
                throw new Config_Action_Exception("Entity key '{$property_name}' cannot be changed by the setProperties config action.");
            }
            $parts = explode('.', (string) $property_name);
            $property_value = $entity->get($parts[0]);
            if (count($parts) > 1) {
                if (isset($property_value) && !is_array($property_value)) {
                    throw new Config_Action_Exception('The setProperties config action can only set nested values on arrays.');
                }
                $property_value ??= [];
                Nested_Array::set_value($property_value, array_slice($parts, 1), $value);
            } else {
                $property_value = $value;
            }
            $entity->set($parts[0], $property_value);
        }
        $entity->save();
    }
}