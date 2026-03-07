<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\Core\Config\Action;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * @internal
 *   This API is experimental.
 */
enum Exists
{
    case ErrorIfExists;
    case ErrorIfNotExists;
    case ReturnEarlyIfExists;
    case ReturnEarlyIfNotExists;

    /**
     * Determines if an action should return early depending on $entity.
     *
     * @param string $configName
     *   The config name supplied to the action.
     * @param \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity
     *   The entity, if it exists.
     *
     * @return bool
     *   TRUE if the action should return early, FALSE if not.
     *
     * @throws \Drupal\Core\Config\Action\ConfigActionException
     *   Thrown depending on $entity and the value of $this.
     */
    public function returnEarly(string $configName, ?ConfigEntityInterface $entity): bool
    {
        return match (true) {
            $this === self::ReturnEarlyIfExists && $entity !== null,
            $this === self::ReturnEarlyIfNotExists && $entity === null => true,
            $this === self::ErrorIfExists && $entity !== null => throw new ConfigActionException(sprintf('Entity %s exists', $configName)),
            $this === self::ErrorIfNotExists && $entity === null => throw new ConfigActionException(sprintf('Entity %s does not exist', $configName)),
            default => false
        };
    }

}
