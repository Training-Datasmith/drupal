<?php

declare (strict_types=1);
namespace Drupal\Core\Cache;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Adds cache_bins parameter to the container.
 */
class List_Cache_Bins_Pass implements Compiler_Pass_Interface
{
    /**
     * Implements CompilerPassInterface::process().
     *
     * Collects the cache bins into the cache_bins parameter.
     */
    public function process(Container_Builder $container): void
    {
        $cache_info['cache']['bins'] = [];
        $cache_info['cache']['default_bin_backends'] = [];
        $cache_info['memory_cache']['bins'] = [];
        $cache_info['memory_cache']['default_bin_backends'] = [];
        $tag_info = ['cache.bin' => 'cache', 'cache.bin.memory' => 'memory_cache'];
        foreach ($tag_info as $service_tag => $section) {
            foreach ($container->find_tagged_service_ids($service_tag) as $id => $attributes) {
                $bin = substr($id, strpos($id, '.') + 1);
                $cache_info[$section]['bins'][$id] = $bin;
                if (isset($attributes[0]['default_backend'])) {
                    $cache_info[$section]['default_bin_backends'][$bin] = $attributes[0]['default_backend'];
                }
            }
        }
        $container->set_parameter('cache_bins', $cache_info['cache']['bins']);
        $container->set_parameter('cache_default_bin_backends', $cache_info['cache']['default_bin_backends']);
        $container->set_parameter('memory_cache_bins', $cache_info['memory_cache']['bins']);
        $container->set_parameter('memory_cache_default_bin_backends', $cache_info['memory_cache']['default_bin_backends']);
    }
}