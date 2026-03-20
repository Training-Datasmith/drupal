<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Action\Plugin\Config_Action\Deriver;

use Drupal\Component\Plugin\Derivative\Deriver_Base;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\String_Translation\String_Translation_Trait;
/**
 * @internal
 *   This API is experimental.
 */
final class Entity_Create_Deriver extends Deriver_Base
{
    use String_Translation_Trait;
    /**
     * {@inheritdoc}
     */
    public function get_derivative_definitions($base_plugin_definition)
    {
        // These derivatives apply to all entity types.
        $base_plugin_definition['entity_types'] = ['*'];
        $this->derivatives['createIfNotExists'] = $base_plugin_definition + ['constructor_args' => ['exists' => Exists::ReturnEarlyIfExists]];
        $this->derivatives['createIfNotExists']['admin_label'] = $this->t('Create entity if it does not exist');
        $this->derivatives['create'] = $base_plugin_definition + ['constructor_args' => ['exists' => Exists::ErrorIfExists]];
        $this->derivatives['create']['admin_label'] = $this->t('Entity create');
        return $this->derivatives;
    }
}