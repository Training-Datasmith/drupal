<?php

declare (strict_types=1);
namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\Derivative\Entity_Delete_Action_Deriver;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Temp_Store\Private_Temp_Store_Factory;
/**
 * Redirects to an entity deletion form.
 */
#[Action(id: 'entity:delete_action', action_label: new Translatable_Markup('Delete'), deriver: Entity_Delete_Action_Deriver::class)]
class Delete_Action extends Entity_Action_Base
{
    /**
     * The tempstore object.
     *
     * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
     */
    protected $temp_store;
    /**
     * Constructs a new DeleteAction object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
     *   The tempstore factory.
     * @param \Drupal\Core\Session\AccountInterface $currentUser
     *   Current user.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, Entity_Type_Manager_Interface $entity_type_manager, Private_Temp_Store_Factory $temp_store_factory, protected \Drupal\Core\Session\Account_Interface $current_user)
    {
        $this->temp_store = $temp_store_factory->get('entity_delete_multiple_confirm');
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    }
    /**
     * {@inheritdoc}
     */
    public function execute_multiple(array $entities): void
    {
        /** @var \Drupal\Core\Entity\EntityInterface[] $entities */
        $selection = [];
        foreach ($entities as $entity) {
            $langcode = $entity->language()->get_id();
            $selection[$entity->id()][$langcode] = $langcode;
        }
        $this->temp_store->set($this->current_user->id() . ':' . $this->get_plugin_definition()['type'], $selection);
    }
    /**
     * {@inheritdoc}
     */
    public function execute($object = null): void
    {
        $this->execute_multiple([$object]);
    }
    /**
     * {@inheritdoc}
     */
    public function access($object, ?Account_Interface $account = null, $return_as_object = false)
    {
        return $object->access('delete', $account, $return_as_object);
    }
}