<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the user schema handler.
 */
class UserStorageSchema extends SqlContentEntityStorageSchema
{
    /**
     * {@inheritdoc}
     */
    protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = false)
    {
        $schema = parent::getEntitySchema($entity_type, $reset);

        if ($data_table = $this->storage->getDataTable()) {
            $schema[$data_table]['unique keys'] += [
              'user__name' => ['name', 'langcode'],
            ];
        }

        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array
    {
        $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
        $field_name = $storage_definition->getName();

        if ($table_name == 'users_field_data') {
            switch ($field_name) {
                case 'name':
                    // Improves the performance of the user__name index defined
                    // in getEntitySchema().
                    $schema['fields'][$field_name]['not null'] = true;
                    // Make sure the field is no longer than 191 characters so we can
                    // add a unique constraint in MySQL.
                    $schema['fields'][$field_name]['length'] = UserInterface::USERNAME_MAX_LENGTH;
                    break;

                case 'mail':
                    $this->addSharedTableFieldIndex($storage_definition, $schema);
                    break;

                case 'access':
                case 'created':
                    $this->addSharedTableFieldIndex($storage_definition, $schema, true);
                    break;
            }
        }

        return $schema;
    }

}
