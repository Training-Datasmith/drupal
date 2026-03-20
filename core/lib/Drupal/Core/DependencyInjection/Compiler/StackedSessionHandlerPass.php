<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Provides a compiler pass for stacked session save handlers.
 */
class Stacked_Session_Handler_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if ($container->has_definition('session_handler')) {
            return;
        }
        $session_handler_proxies = [];
        $priorities = [];
        foreach ($container->find_tagged_service_ids('session_handler_proxy') as $id => $attributes) {
            $priorities[$id] = $attributes[0]['priority'] ?? 0;
            $session_handler_proxies[$id] = $container->get_definition($id);
        }
        array_multisort($priorities, SORT_ASC, $session_handler_proxies);
        $decorated_id = 'session_handler.storage';
        foreach ($session_handler_proxies as $id => $decorator) {
            // Prepend the inner session handler as first constructor argument.
            $arguments = $decorator->get_arguments();
            array_unshift($arguments, new Reference($decorated_id));
            $decorator->set_arguments($arguments);
            $decorated_id = $id;
        }
        $container->set_alias('session_handler', $decorated_id);
    }
}