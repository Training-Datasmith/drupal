<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action;

use Drupal\Core\Config\Action\Attribute\Config_Action;
use Drupal\Core\Config\Action\Config_Action_Exception;
use Drupal\Core\Config\Action\Config_Action_Plugin_Interface;
use Drupal\Core\Config\Config_Factory_Interface;
use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @internal
 *   This API is experimental.
 */
#[Config_Action(id: 'simpleConfigUpdate', admin_label: new Translatable_Markup('Simple configuration update'))]
final readonly class Simple_Config_Update implements Config_Action_Plugin_Interface, Container_Factory_Plugin_Interface
{
    public function __construct(private Config_Factory_Interface $config_factory, private Config_Manager_Interface $config_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        return new static($container->get(Config_Factory_Interface::class), $container->get(Config_Manager_Interface::class));
    }
    /**
     * {@inheritdoc}
     */
    public function apply(string $config_name, mixed $value): void
    {
        if ($this->config_manager->get_entity_type_id_by_name($config_name)) {
            // @todo Make this an exception in https://www.drupal.org/node/3515544.
            @trigger_error('Using the simpleConfigUpdate config action on config entities is deprecated in drupal:11.2.0 and throws an exception in drupal:12.0.0. Use the setProperties action instead. See https://www.drupal.org/node/3515543', E_USER_DEPRECATED);
        }
        $config = $this->config_factory->get_editable($config_name);
        if ($config->is_new()) {
            throw new Config_Action_Exception(sprintf('Config %s does not exist so can not be updated', $config_name));
        }
        // Expect $value to be an array whose keys are the config keys to update.
        if (!is_array($value)) {
            throw new Config_Action_Exception(sprintf('Config %s can not be updated because $value is not an array', $config_name));
        }
        foreach ($value as $key => $value) {
            $config->set($key, $value);
        }
        $config->save();
    }
}