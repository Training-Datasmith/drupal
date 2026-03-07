<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableInterface;

/**
 * A stub revisionable entity for testing purposes.
 */
class StubRevisionableEntity extends StubEntityBase implements RevisionableInterface
{
    /**
     * {@inheritdoc}
     */
    public function isNewRevision(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setNewRevision($value = true): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getRevisionId(): int|string|null
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoadedRevisionId(): ?int
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function updateLoadedRevisionId(): static
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isDefaultRevision($new_value = null): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function wasDefaultRevision(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isLatestRevision(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record): void
    {
    }

}
