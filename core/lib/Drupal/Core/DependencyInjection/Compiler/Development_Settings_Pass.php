<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Drupal\Core\Cache\Null_Backend_Factory;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Defines a compiler pass to register development settings.
 */
class Development_Settings_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $development_settings */
        $development_settings = $container->get('keyvalue')->get('development_settings');
        $twig_debug = $development_settings->get('twig_debug', false);
        $twig_cache_disable = $development_settings->get('twig_cache_disable', false);
        if ($twig_debug || $twig_cache_disable) {
            $twig_config = $container->get_parameter('twig.config');
            $twig_config['debug'] = $twig_debug;
            $twig_config['cache'] = !$twig_cache_disable;
            $container->set_parameter('twig.config', $twig_config);
        }
        if ($development_settings->get('disable_rendered_output_cache_bins', false)) {
            $cache_bins = ['page', 'dynamic_page_cache', 'render'];
            if (!$container->has_definition('cache.backend.null')) {
                $container->register('cache.backend.null', Null_Backend_Factory::class);
            }
            foreach ($cache_bins as $cache_bin) {
                if ($container->has("cache.{$cache_bin}")) {
                    $container->get_definition("cache.{$cache_bin}")->clear_tag('cache.bin')->add_tag('cache.bin', ['default_backend' => 'cache.backend.null']);
                }
            }
        }
    }
}