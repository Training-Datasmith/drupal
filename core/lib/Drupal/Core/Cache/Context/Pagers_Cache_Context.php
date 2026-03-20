<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\Cacheable_Metadata;
/**
 * Defines a cache context for "per page in a pager" caching.
 *
 * Cache context ID: 'url.query_args.pagers' (to vary by all pagers).
 * Calculated cache context ID: 'url.query_args.pagers:%pager_id', e.g.
 * 'url.query_args.pagers:1' (to vary by the pager with ID 1).
 */
class Pagers_Cache_Context implements Calculated_Cache_Context_Interface
{
    /**
     * Constructs a new PagersCacheContext object.
     *
     * @param \Drupal\Core\Pager\PagerParametersInterface $pagerParams
     *   The pager parameters.
     */
    public function __construct(protected \Drupal\Core\Pager\Pager_Parameters_Interface $pager_params)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function get_label()
    {
        return t('Pager');
    }
    /**
     * {@inheritdoc}
     *
     * @see \Drupal\Core\Pager\PagerParametersInterface::findPage()
     */
    public function get_context($pager_id = null)
    {
        // The value of the 'page' query argument contains the information that
        // controls *all* pagers.
        if ($pager_id === null) {
            return $this->pager_params->get_pager_parameter();
        }
        return $pager_id . '.' . $this->pager_params->find_page($pager_id);
    }
    /**
     * {@inheritdoc}
     */
    public function get_cacheable_metadata($pager_id = null): \Drupal\Core\Cache\Cacheable_Metadata
    {
        return new Cacheable_Metadata();
    }
}