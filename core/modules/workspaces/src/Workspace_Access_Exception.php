<?php

declare(strict_types=1);

namespace Drupal\workspaces;

use Drupal\Core\Access\AccessException;

/**
 * Exception thrown when trying to switch to an inaccessible workspace.
 */
class WorkspaceAccessException extends AccessException
{
}
