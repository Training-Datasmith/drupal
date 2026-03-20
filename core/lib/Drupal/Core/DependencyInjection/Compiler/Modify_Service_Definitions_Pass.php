<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Drupal\Core\Dependency_Injection\Service_Modifier_Interface;
use Drupal\Core\Drupal_Kernel_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Passes the container to the alter() method of all service providers.
 */
class Modify_Service_Definitions_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if (!$container->has('kernel')) {
            return;
        }
        $kernel = $container->get('kernel');
        if (!$kernel instanceof Drupal_Kernel_Interface) {
            return;
        }
        $providers = $kernel->get_service_providers('app');
        foreach ($providers as $provider) {
            if ($provider instanceof Service_Modifier_Interface) {
                $provider->alter($container);
            }
        }
        $providers = $kernel->get_service_providers('site');
        foreach ($providers as $provider) {
            if ($provider instanceof Service_Modifier_Interface) {
                $provider->alter($container);
            }
        }
    }
}