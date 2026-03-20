<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\Derivative\Entity_Changed_Action_Deriver;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Provides an action that can save any entity.
 */
#[Action(id: 'entity:save_action', action_label: new Translatable_Markup('Save'), deriver: Entity_Changed_Action_Deriver::class)]
class Save_Action extends Entity_Action_Base
{
    /**
     * Constructs a SaveAction object.
     *
     * @param mixed[] $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, Entity_Type_Manager_Interface $entity_type_manager, protected \Drupal\Component\Datetime\Time_Interface $time)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    }
    /**
     * {@inheritdoc}
     */
    public function execute($entity = null): void
    {
        $entity->set_changed_time($this->time->get_request_time())->save();
    }
    /**
     * {@inheritdoc}
     */
    public function access($object, ?Account_Interface $account = null, $return_as_object = false)
    {
        // It's not necessary to check the changed field access here, because
        // Drupal\Core\Field\ChangedFieldItemList would anyway return 'not allowed'.
        // Also changing the changed field value is only a workaround to trigger an
        // entity resave. Without a field change, this would not be possible.
        /** @var \Drupal\Core\Entity\EntityInterface $object */
        return $object->access('update', $account, $return_as_object);
    }
}