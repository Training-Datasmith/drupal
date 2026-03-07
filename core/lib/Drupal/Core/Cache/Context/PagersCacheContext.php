<?php

declare(strict_types=1);

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines a cache context for "per page in a pager" caching.
 *
 * Cache context ID: 'url.query_args.pagers' (to vary by all pagers).
 * Calculated cache context ID: 'url.query_args.pagers:%pager_id', e.g.
 * 'url.query_args.pagers:1' (to vary by the pager with ID 1).
 */
class PagersCacheContext implements CalculatedCacheContextInterface
{
    /**
     * Constructs a new PagersCacheContext object.
     *
     * @param \Drupal\Core\Pager\PagerParametersInterface $pagerParams
     *   The pager parameters.
     */
    public function __construct(protected \Drupal\Core\Pager\PagerParametersInterface $pagerParams)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getLabel()
    {
        return t('Pager');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Drupal\Core\Pager\PagerParametersInterface::findPage()
     */
    public function getContext($pager_id = null)
    {
        // The value of the 'page' query argument contains the information that
        // controls *all* pagers.
        if ($pager_id === null) {
            return $this->pagerParams->getPagerParameter();
        }

        return $pager_id . '.' . $this->pagerParams->findPage($pager_id);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata($pager_id = null): \Drupal\Core\Cache\CacheableMetadata
    {
        return new CacheableMetadata();
    }

}
