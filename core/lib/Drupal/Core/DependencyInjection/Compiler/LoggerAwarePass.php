<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Psr\Log\Logger_Aware_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Sets the logger on all services that implement LoggerAwareInterface.
 */
class Logger_Aware_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $interface = Logger_Aware_Interface::class;
        foreach ($container->find_tagged_service_ids('logger_aware') as $id => $attributes) {
            $definition = $container->get_definition($id);
            // Skip services that are already calling setLogger().
            if ($definition->has_method_call('setLogger')) {
                continue;
            }
            if (!is_subclass_of($definition->get_class(), $interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }
            $provider_tag = $definition->get_tag('_provider');
            $logger_id = 'logger.channel.' . $provider_tag[0]['provider'];
            if ($container->has($logger_id)) {
                $definition->add_method_call('setLogger', [new Reference($logger_id)]);
            }
        }
    }
}