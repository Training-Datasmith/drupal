<?php

declare (strict_types=1);
namespace Drupal\Core;

use Drupal\Core\Cache\Context\Cache_Contexts_Pass;
use Drupal\Core\Cache\List_Cache_Bins_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Authentication_Provider_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Backend_Compiler_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Backwards_Compatibility_Class_Loader_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Cors_Compiler_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Deprecated_Service_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Development_Settings_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Logger_Aware_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Modify_Service_Definitions_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Proxy_Services_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Register_Access_Checks_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Register_Event_Subscribers_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Register_Services_For_Destruction_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Register_Stream_Wrappers_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Stacked_Kernel_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Stacked_Session_Handler_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Super_User_Access_Policy_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Tagged_Handlers_Pass;
use Drupal\Core\Dependency_Injection\Compiler\Twig_Extension_Pass;
use Drupal\Core\Dependency_Injection\Container_Builder;
use Drupal\Core\Dependency_Injection\Service_Modifier_Interface;
use Drupal\Core\Dependency_Injection\Service_Provider_Interface;
use Drupal\Core\Extension\Module_Uninstall_Validator_Interface;
use Drupal\Core\Hook\Hook_Collector_Key_Value_Write_Pass;
use Drupal\Core\Hook\Hook_Collector_Pass;
use Drupal\Core\Hook\Theme_Hook_Collector_Pass;
use Drupal\Core\Plugin\Plugin_Manager_Pass;
use Drupal\Core\Pre_Warm\Pre_Warmable_Interface;
use Drupal\Core\Queue\Queue_Factory_Interface;
use Drupal\Core\Render\Main_Content\Main_Content_Renderers_Pass;
use Drupal\Core\Site\Settings;
use Psr\Log\Logger_Aware_Interface;
use Symfony\Component\Dependency_Injection\Compiler\Pass_Config;
use Symfony\Component\Event_Dispatcher\Dependency_Injection\Register_Listeners_Pass;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * ServiceProvider class for mandatory core services.
 *
 * This is where Drupal core registers all of its compiler passes.
 * The service definitions themselves are in core/core.services.yml with a
 * few, documented exceptions (typically, install requirements).
 *
 * Modules wishing to register services to the container should use
 * modulename.services.yml in their respective directories.
 *
 * @ingroup container
 */
class Core_Service_Provider implements Service_Provider_Interface, Service_Modifier_Interface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container_Builder $container): void
    {
        // Only register the private file stream wrapper if a file path has been
        // set.
        if (Settings::get('file_private_path')) {
            $container->register('stream_wrapper.private', \Drupal\Core\Stream_Wrapper\Private_Stream::class)->add_tag('stream_wrapper', ['scheme' => 'private']);
        }
        $container->add_compiler_pass(new Hook_Collector_Pass());
        $container->add_compiler_pass(new Theme_Hook_Collector_Pass());
        $container->add_compiler_pass(new Hook_Collector_Key_Value_Write_Pass(), Pass_Config::TYPE_OPTIMIZE);
        // Add the compiler pass that lets service providers modify existing
        // service definitions. This pass must come before all passes operating on
        // services so that later list-building passes are operating on the
        // post-alter services list.
        $container->add_compiler_pass(new Modify_Service_Definitions_Pass());
        $container->add_compiler_pass(new Development_Settings_Pass());
        $container->add_compiler_pass(new Super_User_Access_Policy_Pass());
        $container->add_compiler_pass(new Proxy_Services_Pass());
        $container->add_compiler_pass(new Backend_Compiler_Pass());
        $container->add_compiler_pass(new Cors_Compiler_Pass());
        $container->add_compiler_pass(new Stacked_Kernel_Pass());
        $container->add_compiler_pass(new Stacked_Session_Handler_Pass());
        $container->add_compiler_pass(new Main_Content_Renderers_Pass());
        // Collect tagged handler services as method calls on consumer services.
        $container->add_compiler_pass(new Tagged_Handlers_Pass());
        $container->add_compiler_pass(new Register_Stream_Wrappers_Pass());
        $container->add_compiler_pass(new Twig_Extension_Pass());
        // Add a compiler pass for registering event subscribers.
        $container->add_compiler_pass(new Register_Event_Subscribers_Pass(new Register_Listeners_Pass()), Pass_Config::TYPE_AFTER_REMOVING);
        $container->add_compiler_pass(new Logger_Aware_Pass(), Pass_Config::TYPE_AFTER_REMOVING);
        $container->add_compiler_pass(new Register_Access_Checks_Pass());
        // Add a compiler pass for registering services needing destruction.
        $container->add_compiler_pass(new Register_Services_For_Destruction_Pass());
        // Add the compiler pass that will process the tagged services.
        $container->add_compiler_pass(new List_Cache_Bins_Pass());
        $container->add_compiler_pass(new Cache_Contexts_Pass());
        $container->add_compiler_pass(new Authentication_Provider_Pass());
        // Register plugin managers.
        $container->add_compiler_pass(new Plugin_Manager_Pass());
        $container->add_compiler_pass(new Deprecated_Service_Pass());
        // Collect moved classes for the backwards compatibility class loader.
        $container->add_compiler_pass(new Backwards_Compatibility_Class_Loader_Pass());
        $container->register_for_autoconfiguration(Event_Subscriber_Interface::class)->add_tag('event_subscriber');
        $container->register_for_autoconfiguration(Logger_Aware_Interface::class)->add_tag('logger_aware');
        $container->register_for_autoconfiguration(Queue_Factory_Interface::class)->add_tag('queue_factory');
        $container->register_for_autoconfiguration(Pre_Warmable_Interface::class)->add_tag('cache_prewarmable');
        $container->register_for_autoconfiguration(Module_Uninstall_Validator_Interface::class)->add_tag('module_install.uninstall_validator');
    }
    /**
     * Alters the UUID service to use the most efficient method available.
     *
     * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
     *   The container builder.
     */
    public function alter(Container_Builder $container): void
    {
        $uuid_service = $container->get_definition('uuid');
        // Debian/Ubuntu uses the (broken) OSSP extension as their UUID
        // implementation. The OSSP implementation is not compatible with the
        // PECL functions.
        if (function_exists('uuid_create') && !function_exists('uuid_make')) {
            $uuid_service->set_class(\Drupal\Component\Uuid\Pecl::class);
        } elseif (function_exists('com_create_guid')) {
            $uuid_service->set_class(\Drupal\Component\Uuid\Com::class);
        }
    }
}