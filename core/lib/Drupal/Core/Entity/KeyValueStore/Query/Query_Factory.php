<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\KeyValueStore\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\QueryFactoryInterface;

/**
 * Provides a factory for creating the key value entity query.
 */
class QueryFactory implements QueryFactoryInterface
{
    /**
     * The namespace of this class, the parent class etc.
     *
     * @var array
     */
    protected $namespaces;

    /**
     * Constructs a QueryFactory object.
     */
    public function __construct(/**
   * The key value factory.
   */
        protected \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
    ) {
        $this->namespaces = Query::getNamespaces($this);
    }

    /**
     * {@inheritdoc}
     */
    public function get(EntityTypeInterface $entity_type, $conjunction): \Drupal\Core\Entity\KeyValueStore\Query\Query
    {
        return new Query($entity_type, $conjunction, $this->namespaces, $this->keyValueFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregate(EntityTypeInterface $entity_type, $conjunction): never
    {
        throw new QueryException('Aggregation over key-value entity storage is not supported');
    }

}
