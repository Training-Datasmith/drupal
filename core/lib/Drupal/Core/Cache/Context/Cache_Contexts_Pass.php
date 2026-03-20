<?php

declare (strict_types=1);
namespace Drupal\Core\Cache\Context;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Adds cache_contexts parameter to the container.
 */
class Cache_Contexts_Pass implements Compiler_Pass_Interface
{
    /**
     * Implements CompilerPassInterface::process().
     *
     * Collects the cache contexts into the cache_contexts parameter.
     */
    public function process(Container_Builder $container): void
    {
        $cache_contexts = [];
        foreach (array_keys($container->find_tagged_service_ids('cache.context')) as $id) {
            if (!str_starts_with((string) $id, 'cache_context.')) {
                throw new \InvalidArgumentException(sprintf('The service "%s" has an invalid service ID: cache context service IDs must use the "cache_context." prefix. (The suffix is the cache context ID developers may use.)', $id));
            }
            $cache_contexts[] = substr((string) $id, 14);
        }
        // Validate.
        sort($cache_contexts);
        foreach ($cache_contexts as $id) {
            // Validate the hierarchy of non-root-level cache contexts.
            if (str_contains($id, '.')) {
                $parent = substr($id, 0, strrpos($id, '.'));
                if (!in_array($parent, $cache_contexts)) {
                    throw new \InvalidArgumentException(sprintf('The service "%s" has an invalid service ID: the period indicates the hierarchy of cache contexts, therefore "%s" is considered the parent cache context, but no cache context service with that name was found.', $id, $parent));
                }
            }
        }
        $container->set_parameter('cache_contexts', $cache_contexts);
    }
}