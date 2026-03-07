<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Passes the container to the alter() method of all service providers.
 */
class ModifyServiceDefinitionsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('kernel')) {
            return;
        }

        $kernel = $container->get('kernel');
        if (!($kernel instanceof DrupalKernelInterface)) {
            return;
        }
        $providers = $kernel->getServiceProviders('app');
        foreach ($providers as $provider) {
            if ($provider instanceof ServiceModifierInterface) {
                $provider->alter($container);
            }
        }
        $providers = $kernel->getServiceProviders('site');
        foreach ($providers as $provider) {
            if ($provider instanceof ServiceModifierInterface) {
                $provider->alter($container);
            }
        }
    }

}
