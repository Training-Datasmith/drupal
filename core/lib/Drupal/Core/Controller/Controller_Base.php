<?php

declare (strict_types=1);
namespace Drupal\Core\Controller;

use Drupal\Core\Dependency_Injection\Autowire_Trait;
use Drupal\Core\Dependency_Injection\Container_Injection_Interface;
use Drupal\Core\Logger\Logger_Channel_Trait;
use Drupal\Core\Messenger\Messenger_Trait;
use Drupal\Core\Routing\Redirect_Destination_Trait;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\Url;
use Symfony\Component\Http_Foundation\Redirect_Response;
/**
 * Utility base class for thin controllers.
 *
 * Controllers that use this base class have access to a number of utility
 * methods and to the Container, which can greatly reduce boilerplate dependency
 * handling code.  However, it also makes the class considerably more
 * difficult to unit test. Therefore this base class should only be used by
 * controller classes that contain only trivial glue code.  Controllers that
 * contain sufficiently complex logic that it's worth testing should not use
 * this base class but use ContainerInjectionInterface instead, or even
 * better be refactored to be trivial glue code.
 *
 * The services exposed here are those that it is reasonable for a well-behaved
 * controller to leverage. A controller that needs other services may
 * need to be refactored into a thin controller and a dependent unit-testable
 * service.
 *
 * @see \Drupal\Core\DependencyInjection\ContainerInjectionInterface
 *
 * @ingroup routing
 */
abstract class Controller_Base implements Container_Injection_Interface
{
    use Autowire_Trait;
    use Logger_Channel_Trait;
    use Messenger_Trait;
    use Redirect_Destination_Trait;
    use String_Translation_Trait;
    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entity_type_manager;
    /**
     * The entity form builder.
     *
     * @var \Drupal\Core\Entity\EntityFormBuilderInterface
     */
    protected $entity_form_builder;
    /**
     * The language manager.
     *
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $language_manager;
    /**
     * The configuration factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $config_factory;
    /**
     * The key-value storage.
     *
     * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     */
    protected $key_value;
    /**
     * The current user service.
     *
     * @var \Drupal\Core\Session\AccountInterface
     */
    protected $current_user;
    /**
     * The state service.
     *
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state_service;
    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $module_handler;
    /**
     * The form builder.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $form_builder;
    /**
     * Retrieves the entity type manager.
     *
     * @return \Drupal\Core\Entity\EntityTypeManagerInterface
     *   The entity type manager.
     */
    protected function entity_type_manager()
    {
        if (!isset($this->entity_type_manager)) {
            $this->entity_type_manager = $this->container()->get('entity_type.manager');
        }
        return $this->entity_type_manager;
    }
    /**
     * Retrieves the entity form builder.
     *
     * @return \Drupal\Core\Entity\EntityFormBuilderInterface
     *   The entity form builder.
     */
    protected function entity_form_builder()
    {
        if (!$this->entity_form_builder) {
            $this->entity_form_builder = $this->container()->get('entity.form_builder');
        }
        return $this->entity_form_builder;
    }
    /**
     * Returns the requested cache bin.
     *
     * @param string $bin
     *   (optional) The cache bin for which the cache object should be returned,
     *   defaults to 'default'.
     *
     * @return \Drupal\Core\Cache\CacheBackendInterface
     *   The cache object associated with the specified bin.
     */
    protected function cache(string $bin = 'default')
    {
        return $this->container()->get('cache.' . $bin);
    }
    /**
     * Retrieves a configuration object.
     *
     * This is the main entry point to the configuration API. Calling
     * $this->config('my_module.admin') will return a configuration object in
     * which the my_module module can store its administrative settings.
     *
     * @param string $name
     *   The name of the configuration object to retrieve. The name corresponds to
     *   a configuration file. For \Drupal::config('my_module.admin'), the config
     *   object returned will contain the contents of my_module.admin
     *   configuration file.
     *
     * @return \Drupal\Core\Config\Config
     *   A configuration object.
     */
    protected function config($name)
    {
        if (!$this->config_factory) {
            $this->config_factory = $this->container()->get('config.factory');
        }
        return $this->config_factory->get($name);
    }
    /**
     * Returns a key/value storage collection.
     *
     * @param string $collection
     *   Name of the key/value collection to return.
     *
     * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     *   The key/value storage.
     */
    protected function key_value($collection)
    {
        if (!$this->key_value) {
            $this->key_value = $this->container()->get('keyvalue')->get($collection);
        }
        return $this->key_value;
    }
    /**
     * Returns the state storage service.
     *
     * Use this to store machine-generated data, local to a specific environment
     * that does not need deploying and does not need human editing; for example,
     * the last time cron was run. Data which needs to be edited by humans and
     * needs to be the same across development, production, etc. environments
     * (for example, the system maintenance message) should use config() instead.
     *
     * @return \Drupal\Core\State\StateInterface
     *   The state storage service.
     */
    protected function state()
    {
        if (!$this->state_service) {
            $this->state_service = $this->container()->get('state');
        }
        return $this->state_service;
    }
    /**
     * Returns the module handler.
     *
     * @return \Drupal\Core\Extension\ModuleHandlerInterface
     *   The module handler service.
     */
    protected function module_handler()
    {
        if (!$this->module_handler) {
            $this->module_handler = $this->container()->get('module_handler');
        }
        return $this->module_handler;
    }
    /**
     * Returns the form builder service.
     *
     * @return \Drupal\Core\Form\FormBuilderInterface
     *   The form builder service.
     */
    protected function form_builder()
    {
        if (!$this->form_builder) {
            $this->form_builder = $this->container()->get('form_builder');
        }
        return $this->form_builder;
    }
    /**
     * Returns the current user.
     *
     * @return \Drupal\Core\Session\AccountInterface
     *   The current user.
     */
    protected function current_user()
    {
        if (!$this->current_user) {
            $this->current_user = $this->container()->get('current_user');
        }
        return $this->current_user;
    }
    /**
     * Returns the language manager service.
     *
     * @return \Drupal\Core\Language\LanguageManagerInterface
     *   The language manager.
     */
    protected function language_manager()
    {
        if (!$this->language_manager) {
            $this->language_manager = $this->container()->get('language_manager');
        }
        return $this->language_manager;
    }
    /**
     * Returns a redirect response object for the specified route.
     *
     * @param string $route_name
     *   The name of the route to which to redirect.
     * @param array $route_parameters
     *   (optional) Parameters for the route.
     * @param array $options
     *   (optional) An associative array of additional options.
     * @param int $status
     *   (optional) The HTTP redirect status code for the redirect. The default is
     *   302 Found.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *   A redirect response object that may be returned by the controller.
     */
    protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302)
    {
        $options['absolute'] = true;
        return new Redirect_Response(Url::from_route($route_name, $route_parameters, $options)->to_string(), $status);
    }
    /**
     * Returns the service container.
     *
     * This method is marked private to prevent sub-classes from retrieving
     * services from the container through it. Instead,
     * \Drupal\Core\DependencyInjection\ContainerInjectionInterface should be used
     * for injecting services.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     *   The service container.
     */
    private function container()
    {
        return \Drupal::get_container();
    }
}