<?php

declare(strict_types=1);

namespace Drupal\workspaces\Entity\Handler;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a handler for entity types that are ignored by workspaces.
 *
 * @internal
 */
class IgnoredWorkspaceHandler implements WorkspaceHandlerInterface, EntityHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static
    {
        return new static();
    }

    /**
     * {@inheritdoc}
     */
    public function isEntitySupported(EntityInterface $entity): bool
    {
        return false;
    }

}
