<?php

declare (strict_types=1);
namespace Drupal\Core\Command;

use Drupal\Core\Drupal_Kernel;
use Drupal\Core\Drupal_Kernel_Interface;
use Drupal\Core\Site\Settings;
use Symfony\Component\Console\Console_Events;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher_Interface as ComponentEventDispatcherInterface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Terminable_Interface;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * Contains helper methods for console commands that boot up Drupal.
 */
trait Bootable_Command_Trait
{
    /**
     * The class loader.
     */
    protected object $class_loader;
    /**
     * Boots up a Drupal environment.
     *
     * @return \Drupal\Core\DrupalKernelInterface
     *   The Drupal kernel.
     *
     * @throws \Exception
     *   Exception thrown if kernel does not boot.
     */
    protected function boot(): Drupal_Kernel_Interface
    {
        $kernel = new Drupal_Kernel('prod', $this->class_loader);
        $kernel::boot_environment();
        $kernel->set_site_path($this->get_site_path());
        Settings::initialize($kernel->get_app_root(), $kernel->get_site_path(), $this->class_loader);
        $kernel->boot();
        // The request needs to be created with a URL that, even if not actually
        // reachable, at least has a valid *form*, so that Drupal can correctly
        // generate links and URLs.
        $request = Request::create('http://' . basename($kernel->get_site_path()) . '/core/scripts/drupal');
        $kernel->pre_handle($request);
        // Try to register an event listener to properly terminate the Drupal kernel
        // when the console application itself terminates. This ensures that
        // `kernel.destructable_services` are destructed, which in turn ensures that
        // the router can be rebuilt if needed, along with other services that
        // perform actions on destruct.
        $event_dispatcher = $kernel->get_container()->get(Event_Dispatcher_Interface::class);
        if ($kernel instanceof Terminable_Interface && $event_dispatcher instanceof Component_Event_Dispatcher_Interface) {
            $event_dispatcher->add_listener(Console_Events::TERMINATE, function () use ($kernel, $request): void {
                $kernel->terminate($request, new Response());
            });
            $this->get_application()->set_dispatcher($event_dispatcher);
        }
        return $kernel;
    }
    /**
     * Gets the site path.
     *
     * Defaults to 'sites/default'. For testing purposes this can be overridden
     * using the DRUPAL_DEV_SITE_PATH environment variable.
     *
     * @return string
     *   The site path to use.
     */
    protected function get_site_path(): string
    {
        return getenv('DRUPAL_DEV_SITE_PATH') ?: 'sites/default';
    }
}