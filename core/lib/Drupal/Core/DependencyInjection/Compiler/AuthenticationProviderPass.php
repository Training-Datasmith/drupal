<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Registers the authentication_providers container parameter.
 */
class Authentication_Provider_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        $authentication_providers = [];
        foreach ($container->find_tagged_service_ids('authentication_provider') as $service_id => $attributes) {
            $authentication_provider = $attributes[0]['provider_id'];
            if ($provider_tag = $container->get_definition($service_id)->get_tag('_provider')) {
                $authentication_providers[$authentication_provider] = $provider_tag[0]['provider'];
            }
        }
        $container->set_parameter('authentication_providers', $authentication_providers);
    }
}