<?php

declare(strict_types=1);

namespace Drupal\Core\KeyValueStore;

use Drupal\Core\Database\Connection;

/**
 * Defines the key/value store factory for the database backend.
 */
class KeyValueDatabaseFactory implements KeyValueFactoryInterface
{
    /**
     * Constructs this factory object.
     *
     * @param \Drupal\Component\Serialization\SerializationInterface $serializer
     *   The serialization class to use.
     * @param \Drupal\Core\Database\Connection $connection
     *   The Connection object containing the key-value tables.
     */
    public function __construct(protected \Drupal\Component\Serialization\SerializationInterface $serializer, protected \Drupal\Core\Database\Connection $connection)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get($collection): \Drupal\Core\KeyValueStore\DatabaseStorage
    {
        return new DatabaseStorage($collection, $this->serializer, $this->connection);
    }

}
