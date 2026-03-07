<?php

declare(strict_types=1);

namespace Drupal\user\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for user.
 */
class UserViewsExecutionHooks
{
    /**
     * Implements hook_views_query_substitutions().
     *
     * Allow replacement of current user ID so we can cache these queries.
     */
    #[Hook('views_query_substitutions')]
    public function viewsQuerySubstitutions(ViewExecutable $view): array
    {
        return ['***CURRENT_USER***' => \Drupal::currentUser()->id()];
    }

}
