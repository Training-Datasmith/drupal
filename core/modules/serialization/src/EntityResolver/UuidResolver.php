<?php

namespace Drupal\serialization\EntityResolver;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Resolves entities from data that contains an entity UUID.
 */
class UuidResolver implements EntityResolverInterface {

  /**
   * Constructs a UuidResolver object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(protected \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(NormalizerInterface $normalizer, $data, $entity_type) {
    if (!$normalizer instanceof UuidReferenceInterface) {
        return NULL;
    }
    if (!$uuid = $normalizer->getUuid($data)) {
        return NULL;
    }
    if ($entity = $this->entityRepository->loadEntityByUuid($entity_type, $uuid)) {
      return $entity->id();
    }
    return NULL;
  }

}
