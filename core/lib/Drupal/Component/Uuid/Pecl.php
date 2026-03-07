<?php

declare(strict_types=1);

namespace Drupal\Component\Uuid;

/**
 * Generates a UUID using the PECL extension.
 */
class Pecl implements UuidInterface
{
    /**
     * {@inheritdoc}
     */
    public function generate(): string
    {
        return strtolower(uuid_create(UUID_TYPE_DEFAULT));
    }

}
