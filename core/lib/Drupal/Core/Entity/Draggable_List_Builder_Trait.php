<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Form\Form_State_Interface;
/**
 * Provides a trait for draggable listings of entities.
 *
 * Classes using this trait must implement \Drupal\Core\Form\FormInterface and
 * are expected to set the $formBuilder property in their constructor.
 */
trait Draggable_List_Builder_Trait
{
    /**
     * The key to use for the form element containing the entities.
     *
     * @var string
     */
    protected $entities_key = 'entities';
    /**
     * The entities being listed.
     *
     * @var \Drupal\Core\Entity\EntityInterface[]
     */
    protected $entities = [];
    /**
     * Name of the entity's weight field or FALSE if no field is provided.
     *
     * @var string|bool
     */
    protected $weight_key = false;
    /**
     * The form builder.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $form_builder;
    /**
     * Gets the weight of the given entity.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity to get the weight for.
     *
     * @return int|float
     *   The weight of the entity.
     */
    abstract protected function get_weight(Entity_Interface $entity): int|float;
    /**
     * Sets the weight of an entity.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity whose weight will be set.
     * @param int|float $weight
     *   The weight to set on the entity.
     *
     * @return $this
     */
    abstract protected function set_weight(Entity_Interface $entity, int|float $weight): Entity_Interface;
    /**
     * Builds the header row for the entity listing.
     *
     * @return array
     *   A render array structure for the header.
     *
     * @see \Drupal\Core\Entity\EntityListBuilder::buildHeader()
     */
    public function build_header()
    {
        $header = [];
        if (!empty($this->weight_key)) {
            $header['weight'] = t('Weight');
        }
        return $header + parent::build_header();
    }
    /**
     * Builds a row for an entity in the entity listing.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   The entity for this row of the list.
     *
     * @return array
     *   A render array structure for an entity row.
     *
     * @see \Drupal\Core\Entity\EntityListBuilder::buildRow()
     */
    public function build_row(Entity_Interface $entity)
    {
        $row = [];
        if (!empty($this->weight_key)) {
            // Override default values to markup elements.
            $row['#attributes']['class'][] = 'draggable';
            $row['#weight'] = $this->get_weight($entity);
            // Add weight column.
            $row['weight'] = ['#type' => 'weight', '#title' => t('Weight for @title', ['@title' => $entity->label()]), '#title_display' => 'invisible', '#default_value' => $this->get_weight($entity), '#attributes' => ['class' => ['weight']]];
        }
        return $row + parent::build_row($entity);
    }
    /**
     * Builds a listing of entities for the given entity type.
     *
     * @return array
     *   A render array structure for the entity list.
     *
     * @see \Drupal\Core\Entity\EntityListBuilder::render()
     */
    public function render()
    {
        if (!empty($this->weight_key)) {
            return $this->form_builder->get_form($this);
        }
        return parent::render();
    }
    /**
     * Form constructor.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     *
     * @see \Drupal\Core\Form\FormInterface::buildForm())
     */
    public function build_form(array $form, Form_State_Interface $form_state): array
    {
        $form[$this->entities_key] = ['#type' => 'table', '#header' => $this->build_header(), '#empty' => t('There are no @label yet.', ['@label' => $this->entity_type->get_plural_label()]), '#tabledrag' => [['action' => 'order', 'relationship' => 'sibling', 'group' => 'weight']]];
        $this->entities = $this->load();
        $delta = 10;
        // Change the delta of the weight field if there are more than 20 entities.
        if (!empty($this->weight_key)) {
            $count = count($this->entities);
            if ($count > 20) {
                $delta = ceil($count / 2);
            }
        }
        foreach ($this->entities as $entity) {
            $row = $this->build_row($entity);
            if (isset($row['label'])) {
                $row['label'] = ['#plain_text' => $row['label']];
            }
            if (isset($row['weight'])) {
                $row['weight']['#delta'] = $delta;
            }
            $form[$this->entities_key][$entity->id()] = $row;
        }
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = ['#type' => 'submit', '#value' => t('Save'), '#button_type' => 'primary'];
        return $form;
    }
    /**
     * Form validation handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @see \Drupal\Core\Form\FormInterface::validateForm())
     */
    public function validate_form(array &$form, Form_State_Interface $form_state): void
    {
        // No validation.
    }
    /**
     * Form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @see \Drupal\Core\Form\FormInterface::submitForm()
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     *   If there is a failure when saving the entity.
     */
    public function submit_form(array &$form, Form_State_Interface $form_state): void
    {
        foreach ($form_state->get_value($this->entities_key) as $id => $value) {
            if (isset($this->entities[$id]) && $this->get_weight($this->entities[$id]) != $value['weight']) {
                // Save entity only when its weight was changed.
                $this->set_weight($this->entities[$id], $value['weight']);
                $this->entities[$id]->save();
            }
        }
    }
}