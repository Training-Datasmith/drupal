<?php

declare(strict_types=1);

namespace Drupal\entity_test_update\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_test_update\EntityTestUpdateStorage;
use Drupal\entity_test_update\EntityTestUpdateStorageSchema;

/**
 * Defines the test entity class for testing definition and schema updates.
 *
 * This entity type starts out non-revisionable and non-translatable, but during
 * an update test it can be made revisionable and translatable using the helper
 * methods from
 * \Drupal\Tests\system\Functional\Entity\Traits\EntityDefinitionTestTrait.
 */
#[ContentEntityType(
    id: 'entity_test_update',
    label: new TranslatableMarkup('Test entity update'),
    persistent_cache: false,
    entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'bundle' => 'type',
    'label' => 'name',
    'langcode' => 'langcode',
  ],
    handlers: [
    'storage_schema' => EntityTestUpdateStorageSchema::class,
    'storage' => EntityTestUpdateStorage::class,
  ],
    base_table: 'entity_test_update',
    additional: [
    'content_translation_ui_skip' => true,
  ],
)]
class EntityTestUpdate extends ContentEntityBase
{
    /**
     * {@inheritdoc}
     */
    public static function preCreate(EntityStorageInterface $storage, array &$values)
    {
        parent::preCreate($storage, $values);
        if (empty($values['type'])) {
            $values['type'] = $storage->getEntityTypeId();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {
        // This entity type is used for generating database dumps from Drupal
        // 8.0.0-rc1, which didn't have the entity key base fields defined in
        // the parent class (ContentEntityBase), so we have to duplicate them here.

        $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('integer')
          ->setLabel(new TranslatableMarkup('ID'))
          ->setDescription(new TranslatableMarkup('The ID of the test entity.'))
          ->setReadOnly(true)
          ->setSetting('unsigned', true);

        $fields[$entity_type->getKey('uuid')] = BaseFieldDefinition::create('uuid')
          ->setLabel(new TranslatableMarkup('UUID'))
          ->setDescription(new TranslatableMarkup('The UUID of the test entity.'))
          ->setReadOnly(true);

        $fields[$entity_type->getKey('bundle')] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Type'))
          ->setDescription(new TranslatableMarkup('The bundle of the test entity.'))
          ->setRequired(true);

        if ($entity_type->hasKey('revision')) {
            $fields[$entity_type->getKey('revision')] = BaseFieldDefinition::create('integer')
              ->setLabel(new TranslatableMarkup('Revision ID'))
              ->setReadOnly(true)
              ->setSetting('unsigned', true);
        }

        $fields[$entity_type->getKey('langcode')] = BaseFieldDefinition::create('language')
          ->setLabel(new TranslatableMarkup('Language'));
        if ($entity_type->isRevisionable()) {
            $fields[$entity_type->getKey('langcode')]->setRevisionable(true);
        }
        if ($entity_type->isTranslatable()) {
            $fields[$entity_type->getKey('langcode')]->setTranslatable(true);
        }

        $fields['name'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Name'))
          ->setDescription(new TranslatableMarkup('The name of the test entity.'))
          ->setTranslatable(true)
          ->setRevisionable(true)
          ->setSetting('max_length', 32)
          ->setDisplayOptions('view', [
            'label' => 'hidden',
            'type' => 'string',
            'weight' => -5,
          ])
          ->setDisplayOptions('form', [
            'type' => 'string_textfield',
            'weight' => -5,
          ]);

        $fields['test_single_property'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Field with a single property'))
          ->setTranslatable(true)
          ->setRevisionable(true);

        $fields['test_multiple_properties'] = BaseFieldDefinition::create('multi_value_test')
          ->setLabel(new TranslatableMarkup('Field with multiple properties'))
          ->setTranslatable(true)
          ->setRevisionable(true);

        $fields['test_single_property_multiple_values'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Field with a single property and multiple values'))
          ->setCardinality(2)
          ->setTranslatable(true)
          ->setRevisionable(true);

        $fields['test_multiple_properties_multiple_values'] = BaseFieldDefinition::create('multi_value_test')
          ->setLabel(new TranslatableMarkup('Field with multiple properties and multiple values'))
          ->setCardinality(2)
          ->setTranslatable(true)
          ->setRevisionable(true);

        $fields['test_non_rev_field'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Non Revisionable Field'))
          ->setDescription(new TranslatableMarkup('A non-revisionable test field.'))
          ->setCardinality(1)
          ->setRevisionable(false)
          ->setTranslatable(true);

        $fields['test_non_mul_field'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Non Translatable Field'))
          ->setDescription(new TranslatableMarkup('A non-translatable test field.'))
          ->setCardinality(1)
          ->setRevisionable(true)
          ->setTranslatable(false);

        $fields['test_non_mulrev_field'] = BaseFieldDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Non Revisionable and Translatable Field'))
          ->setDescription(new TranslatableMarkup('A non-revisionable and non-translatable test field.'))
          ->setCardinality(1)
          ->setRevisionable(false)
          ->setTranslatable(false);

        $fields += \Drupal::state()->get('entity_test_update.additional_base_field_definitions', []);
        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions)
    {
        $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);
        $fields += \Drupal::state()->get('entity_test_update.additional_bundle_field_definitions.' . $bundle, []);
        return $fields;
    }

}
