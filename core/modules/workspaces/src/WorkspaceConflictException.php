<?php

declare(strict_types=1);

namespace Drupal\workspaces;

/**
 * An exception thrown when two workspaces are in a conflicting content state.
 */
class WorkspaceConflictException extends \RuntimeException
{
}
