<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Config\Config_Manager_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
/**
 * Lists affected configuration entities by a dependency removal.
 *
 * This trait relies on the StringTranslationTrait.
 */
trait Config_Dependency_Delete_Form_Trait
{
    /**
     * Translates a string to the current language or to a given language.
     *
     * Provided by \Drupal\Core\StringTranslation\StringTranslationTrait.
     */
    abstract protected function t($string, array $args = [], array $options = []);
    /**
     * Adds form elements to list affected configuration entities.
     *
     * @param array $form
     *   The form array to add elements to.
     * @param string $type
     *   The type of dependency being checked. Either 'module', 'theme', 'config'
     *   or 'content'.
     * @param array $names
     *   The specific names to check. If $type equals 'module' or 'theme' then it
     *   should be a list of module names or theme names. In the case of 'config'
     *   or 'content' it should be a list of configuration dependency names.
     * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
     *   The config manager.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     *
     * @see \Drupal\Core\Config\ConfigManagerInterface::getConfigEntitiesToChangeOnDependencyRemoval()
     */
    protected function add_dependency_lists_to_form(array &$form, $type, array $names, Config_Manager_Interface $config_manager, Entity_Type_Manager_Interface $entity_type_manager)
    {
        // Get the dependent entities.
        $dependent_entities = $config_manager->get_config_entities_to_change_on_dependency_removal($type, $names);
        $entity_types = [];
        $form['entity_updates'] = ['#type' => 'details', '#title' => $this->t('Configuration updates'), '#description' => $this->t('The listed configuration will be updated.'), '#open' => true, '#access' => false];
        foreach ($dependent_entities['update'] as $entity) {
            /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface  $entity */
            $entity_type_id = $entity->get_entity_type_id();
            if (!isset($form['entity_updates'][$entity_type_id])) {
                $entity_type = $entity_type_manager->get_definition($entity_type_id);
                // Store the ID and label to sort the entity types and entities later.
                $label = $entity_type->get_label();
                $entity_types[$entity_type_id] = $label;
                $form['entity_updates'][$entity_type_id] = ['#theme' => 'item_list', '#title' => $label, '#items' => []];
            }
            $form['entity_updates'][$entity_type_id]['#items'][$entity->id()] = $entity->label() ?: $entity->id();
        }
        if (!empty($dependent_entities['update'])) {
            $form['entity_updates']['#access'] = true;
            // Add a weight key to the entity type sections.
            asort($entity_types, SORT_FLAG_CASE);
            $weight = 0;
            foreach ($entity_types as $entity_type_id => $label) {
                $form['entity_updates'][$entity_type_id]['#weight'] = $weight;
                // Sort the list of entity labels alphabetically.
                ksort($form['entity_updates'][$entity_type_id]['#items'], SORT_FLAG_CASE);
                $weight++;
            }
        }
        $form['entity_deletes'] = ['#type' => 'details', '#title' => $this->t('Configuration deletions'), '#description' => $this->t('The listed configuration will be deleted.'), '#open' => true, '#access' => false];
        foreach ($dependent_entities['delete'] as $entity) {
            $entity_type_id = $entity->get_entity_type_id();
            if (!isset($form['entity_deletes'][$entity_type_id])) {
                $entity_type = $entity_type_manager->get_definition($entity_type_id);
                // Store the ID and label to sort the entity types and entities later.
                $label = $entity_type->get_label();
                $entity_types[$entity_type_id] = $label;
                $form['entity_deletes'][$entity_type_id] = ['#theme' => 'item_list', '#title' => $label, '#items' => []];
            }
            $form['entity_deletes'][$entity_type_id]['#items'][$entity->id()] = $entity->label() ?: $entity->id();
        }
        if (!empty($dependent_entities['delete'])) {
            $form['entity_deletes']['#access'] = true;
            // Add a weight key to the entity type sections.
            asort($entity_types, SORT_FLAG_CASE);
            $weight = 0;
            foreach ($entity_types as $entity_type_id => $label) {
                if (isset($form['entity_deletes'][$entity_type_id])) {
                    $form['entity_deletes'][$entity_type_id]['#weight'] = $weight;
                    // Sort the list of entity labels alphabetically.
                    ksort($form['entity_deletes'][$entity_type_id]['#items'], SORT_FLAG_CASE);
                    $weight++;
                }
            }
        }
    }
}