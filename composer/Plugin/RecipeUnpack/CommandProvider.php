<?php

declare(strict_types=1);

namespace Drupal\Composer\Plugin\RecipeUnpack;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

/**
 * List of all commands provided by this package.
 *
 * @internal
 */
final class CommandProvider implements CommandProviderCapability
{
    /**
     * {@inheritdoc}
     */
    public function getCommands(): array
    {
        return [new UnpackCommand()];
    }

}
