<?php

declare(strict_types=1);

namespace Drupal\workspaces;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;

/**
 * Defines the WorkspaceCacheContext service, for "per workspace" caching.
 *
 * Cache context ID: 'workspace'.
 */
class WorkspaceCacheContext implements CacheContextInterface
{
    /**
     * Constructs a new WorkspaceCacheContext service.
     *
     * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
     *   The workspace manager.
     */
    public function __construct(protected \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabel()
    {
        return t('Workspace');
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->workspaceManager->hasActiveWorkspace() ? $this->workspaceManager->getActiveWorkspace()->id() : 'live';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata($type = null): \Drupal\Core\Cache\CacheableMetadata
    {
        // The active workspace will always be stored in the user's session.
        $cacheability = new CacheableMetadata();
        $cacheability->addCacheContexts(['session']);

        return $cacheability;
    }

}
