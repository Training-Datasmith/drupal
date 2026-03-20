<?php

declare(strict_types=1);

namespace Drupal\workflows\Exception;

use Drupal\Core\Config\ConfigException;

/**
 * Indicates that a workflow does not contain a required state.
 */
class RequiredStateMissingException extends ConfigException
{
}
