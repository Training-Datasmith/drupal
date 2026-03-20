<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Schema;

use Drupal\Component\Plugin\Discovery\Discovery_Interface;
use Drupal\Component\Plugin\Discovery\Discovery_Trait;
/**
 * Allows YAML files to define config schema types.
 */
class Config_Schema_Discovery implements Discovery_Interface
{
    use Discovery_Trait;
    /**
     * Constructs a ConfigSchemaDiscovery object.
     *
     * @param \Drupal\Core\Config\StorageInterface $schemaStorage
     *   The storage object to use for reading schema data.
     */
    public function __construct(protected \Drupal\Core\Config\Storage_Interface $schema_storage)
    {
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_definitions(): array
    {
        $definitions = [];
        foreach ($this->schema_storage->read_multiple($this->schema_storage->list_all()) as $schema) {
            foreach ($schema as $type => $definition) {
                $definitions[$type] = $definition;
            }
        }
        return $definitions;
    }
}