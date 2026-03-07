<?php

declare(strict_types=1);

namespace Drupal\telephone\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'telephone' field type.
 */
#[FieldType(
    id: 'telephone',
    label: new TranslatableMarkup('Telephone number'),
    description: new TranslatableMarkup('A telephone number, optionally displayed as a telephone link'),
    default_widget: 'telephone_default',
    default_formatter: 'basic_string'
)]
class TelephoneItem extends FieldItemBase
{
    /**
     * The maximum length for a telephone value.
     */
    public const MAX_LENGTH = 256;

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition): array
    {
        return [
          'columns' => [
            'value' => [
              'type' => 'varchar',
              'length' => self::MAX_LENGTH,
            ],
          ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties['value'] = DataDefinition::create('string')
          ->setLabel(new TranslatableMarkup('Telephone number'))
          ->setRequired(true);

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        $value = $this->get('value')->getValue();
        return $value === null || $value === '';
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
        $constraints = parent::getConstraints();

        $constraints[] = $constraint_manager->create('ComplexData', [
          'properties' => [
            'value' => [
              'Length' => [
                'max' => self::MAX_LENGTH,
                'maxMessage' => $this->t('%name: the telephone number may not be longer than @max characters.', [
                  '%name' => $this->getFieldDefinition()
                    ->getLabel(),
                  '@max' => self::MAX_LENGTH,
                ]),
              ],
            ],
          ],
        ]);

        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSampleValue(FieldDefinitionInterface $field_definition)
    {
        $values['value'] = random_int(10 ** 8, 10 ** 9 - 1);
        return $values;
    }

}
