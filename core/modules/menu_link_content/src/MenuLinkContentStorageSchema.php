<?php

declare(strict_types=1);

namespace Drupal\menu_link_content;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the menu_link_content schema handler.
 */
class MenuLinkContentStorageSchema extends SqlContentEntityStorageSchema
{
    /**
     * {@inheritdoc}
     */
    protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping): array
    {
        $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
        $field_name = $storage_definition->getName();

        if ($table_name == $this->storage->getBaseTable()) {
            switch ($field_name) {
                case 'rediscover':
                    $this->addSharedTableFieldIndex($storage_definition, $schema, true);
                    break;
            }
        }

        return $schema;
    }

}
