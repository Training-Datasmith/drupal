<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Drupal\Core\Cache\Context\Cache_Contexts_Manager;
use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Defines the variation cache factory.
 *
 * @ingroup cache
 */
class Variation_Cache_Factory implements Variation_Cache_Factory_Interface
{
    /**
     * Instantiated variation cache bins.
     *
     * @var \Drupal\Core\Cache\VariationCacheInterface[]
     */
    protected $bins = [];
    /**
     * Constructs a new VariationCacheFactory object.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     *   The request stack.
     * @param \Drupal\Core\Cache\CacheFactoryInterface $cacheFactory
     *   The cache factory.
     * @param \Drupal\Core\Cache\Context\CacheContextsManager $cacheContextsManager
     *   The cache contexts manager.
     */
    public function __construct(protected Request_Stack $request_stack, protected Cache_Factory_Interface $cache_factory, protected Cache_Contexts_Manager $cache_contexts_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get($bin)
    {
        if (!isset($this->bins[$bin])) {
            $this->bins[$bin] = new Variation_Cache($this->request_stack, $this->cache_factory->get($bin), $this->cache_contexts_manager);
        }
        return $this->bins[$bin];
    }
}