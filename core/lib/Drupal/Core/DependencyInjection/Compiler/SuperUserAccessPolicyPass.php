<?php

declare (strict_types=1);
namespace Drupal\Core\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Removes the super user access policy when toggled off.
 */
class Super_User_Access_Policy_Pass implements Compiler_Pass_Interface
{
    /**
     * {@inheritdoc}
     */
    public function process(Container_Builder $container): void
    {
        if ($container->get_parameter('security.enable_super_user') === false) {
            $container->remove_definition('access_policy.super_user');
            $container->remove_alias(\Drupal\Core\Session\Super_User_Access_Policy::class);
        }
    }
}