<?php

declare (strict_types=1);
namespace Drupal\Core;

use Composer\Autoload\Class_Loader;
use Drupal\Component\Dependency_Injection\Reverse_Container;
use Drupal\Component\Event_Dispatcher\Event;
use Drupal\Component\File_Cache\File_Cache_Factory;
use Drupal\Component\Serialization\Php_Serialize;
use Drupal\Component\Utility\Url_Helper;
use Drupal\Core\Cache\Database_Backend;
use Drupal\Core\Class_Loader\Backwards_Compatibility_Class_Loader;
use Drupal\Core\Config\Bootstrap_Config_Storage_Factory;
use Drupal\Core\Config\Null_Storage;
use Drupal\Core\Dependency_Injection\Container_Builder;
use Drupal\Core\Dependency_Injection\Service_Modifier_Interface;
use Drupal\Core\Dependency_Injection\Service_Provider_Interface;
use Drupal\Core\Dependency_Injection\Yaml_File_Loader;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\Extension_Discovery;
use Drupal\Core\File\Mime_Type\Mime_Type_Guesser;
use Drupal\Core\Http\Trusted_Hosts_Request_Factory;
use Drupal\Core\Installer\Installer_Kernel;
use Drupal\Core\Installer\Installer_Redirect_Trait;
use Drupal\Core\Language\Language;
use Drupal\Core\Security\Request_Sanitizer;
use Drupal\Core\Site\Settings;
use Drupal\Core\Test\Test_Database;
use Drupal\Drupal_Installed;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Foundation\Session\Session;
use Symfony\Component\Http_Foundation\Session\Storage\Mock_Array_Session_Storage;
use Symfony\Component\Http_Kernel\Exception\Bad_Request_Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Http_Exception_Interface;
use Symfony\Component\Http_Kernel\Terminable_Interface;
/**
 * The DrupalKernel class is the core of Drupal itself.
 *
 * This class is responsible for building the Dependency Injection Container and
 * also deals with the registration of service providers. It allows registered
 * service providers to add their services to the container. Core provides the
 * CoreServiceProvider, which, in addition to registering any core services that
 * cannot be registered in the core.services.yml file, adds any compiler passes
 * needed by core, e.g. for processing tagged services. Each module can add its
 * own service provider, i.e. a class implementing
 * Drupal\Core\DependencyInjection\ServiceProvider, to register services to the
 * container, or modify existing services.
 */
class Drupal_Kernel implements Drupal_Kernel_Interface, Terminable_Interface
{
    use Installer_Redirect_Trait;
    /**
     * Holds the class used for dumping the container to a PHP array.
     *
     * In combination with swapping the container class this is useful to e.g.
     * dump to the human-readable PHP array format to debug the container
     * definition in an easier way.
     *
     * @var string
     */
    protected $php_array_dumper_class = \Drupal\Component\Dependency_Injection\Dumper\Optimized_Php_Array_Dumper::class;
    /**
     * Holds the default bootstrap container definition.
     *
     * @var array
     */
    protected $default_bootstrap_container_definition = ['parameters' => [], 'services' => ['database' => ['class' => \Drupal\Core\Database\Connection::class, 'factory' => 'Drupal\Core\Database\Database::getConnection', 'arguments' => ['default']], 'request_stack' => ['class' => 'Symfony\Component\HttpFoundation\RequestStack'], 'datetime.time' => ['class' => \Drupal\Component\Datetime\Time::class, 'arguments' => ['@request_stack']], 'cache.container' => ['class' => \Drupal\Core\Cache\Database_Backend::class, 'arguments' => ['@database', '@cache_tags_provider.container', 'container', '@serialization.phpserialize', '@datetime.time', Database_Backend::MAXIMUM_NONE]], 'cache_tags_provider.container' => ['class' => \Drupal\Core\Cache\Database_Cache_Tags_Checksum::class, 'arguments' => ['@database']], 'serialization.phpserialize' => ['class' => Php_Serialize::class]]];
    /**
     * Holds the class used for instantiating the bootstrap container.
     *
     * @var string
     */
    protected $bootstrap_container_class = \Drupal\Component\Dependency_Injection\Php_Array_Container::class;
    /**
     * Holds the bootstrap container.
     *
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $bootstrap_container;
    /**
     * Holds the container instance.
     *
     * @var \Drupal\Component\DependencyInjection\ContainerInterface
     */
    protected $container;
    /**
     * Whether the kernel has been booted.
     *
     * @var bool
     */
    protected $booted = false;
    /**
     * Whether essential services have been set up properly by preHandle().
     *
     * @var bool
     */
    protected $prepared = false;
    /**
     * Holds the list of enabled modules.
     *
     * @var array
     *   An associative array whose keys are module names and whose values are
     *   ignored.
     */
    protected $module_list;
    /**
     * List of available modules and installation profiles.
     *
     * @var \Drupal\Core\Extension\Extension[]
     */
    protected $module_data = [];
    /**
     * Holds the list of enabled themes from core.extension config.
     *
     * @var array|null
     *   An associative array whose keys are theme names and whose values are
     *   ignored.
     */
    protected ?array $theme_list = null;
    /**
     * List of available themes.
     *
     * @var \Drupal\Core\Extension\Extension[]
     */
    protected array $theme_extensions = [];
    /**
     * Config storage object used for reading enabled modules configuration.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $config_storage;
    /**
     * Whether the container needs to be rebuilt the next time it is initialized.
     *
     * @var bool
     */
    protected $container_needs_rebuild = false;
    /**
     * Whether the container needs to be dumped once booting is complete.
     *
     * @var bool
     */
    protected $container_needs_dumping;
    /**
     * List of discovered services.yml path names.
     *
     * This is a nested array whose top-level keys are 'app' and 'site', denoting
     * the origin of a service provider. Site-specific providers have to be
     * collected separately, because they need to be processed last, so as to be
     * able to override services from application service providers.
     *
     * @var array
     */
    protected $service_yamls;
    /**
     * List of discovered service provider class names or objects.
     *
     * This is a nested array whose top-level keys are 'app' and 'site', denoting
     * the origin of a service provider. Site-specific providers have to be
     * collected separately, because they need to be processed last, so as to be
     * able to override services from application service providers.
     *
     * Allowing objects is for example used to allow
     * \Drupal\KernelTests\KernelTestBase to register itself as service provider.
     *
     * @var array
     */
    protected $service_provider_classes;
    /**
     * List of instantiated service provider classes.
     *
     * @var array
     *
     * @see \Drupal\Core\DrupalKernel::$serviceProviderClasses
     */
    protected $service_providers;
    /**
     * Whether the PHP environment has been initialized.
     *
     * This legacy phase can only be booted once because it sets session INI
     * settings. If a session has already been started, re-generating these
     * settings would break the session.
     *
     * @var bool
     */
    protected static $is_environment_initialized = false;
    /**
     * The site path directory.
     *
     * Site path is relative to the app root directory.
     * Usually defined as "sites/default".
     *
     * By default, Drupal uses sites/default.
     *
     * @var string
     */
    protected $site_path;
    /**
     * The app root.
     *
     * @var string
     */
    protected $root;
    /**
     * Create a DrupalKernel object from a request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request.
     * @param \Composer\Autoload\ClassLoader $class_loader
     *   The class loader. Normally Composer's ClassLoader, as included by the
     *   front controller, but may also be decorated.
     * @param string $environment
     *   String indicating the environment, e.g. 'prod' or 'dev'.
     * @param bool $allow_dumping
     *   (optional) FALSE to stop the container from being written to or read
     *   from disk. Defaults to TRUE.
     * @param string $app_root
     *   (optional) The path to the application root as a string. If not supplied,
     *   the application root will be computed.
     *
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *   In case the host name in the request is not trusted.
     */
    public static function create_from_request(Request $request, $class_loader, $environment, $allow_dumping = true, $app_root = null): static
    {
        $kernel = new static($environment, $class_loader, $allow_dumping, $app_root);
        static::boot_environment($app_root);
        $kernel->initialize_settings($request);
        return $kernel;
    }
    /**
     * Constructs a DrupalKernel object.
     *
     * @param string $environment
     *   String indicating the environment, e.g. 'prod' or 'dev'.
     * @param \Composer\Autoload\ClassLoader $classLoader
     *   The class loader. Normally \Composer\Autoload\ClassLoader, as included by
     *   the front controller, but may also be decorated.
     * @param bool $allowDumping
     *   (optional) FALSE to stop the container from being written to or read
     *   from disk. Defaults to TRUE.
     * @param string $app_root
     *   (optional) The path to the application root as a string. If not supplied,
     *   the application root will be computed.
     */
    public function __construct(
        /**
         * The environment, e.g. 'testing', 'install'.
         */
        protected $environment,
        /**
         * The class loader object.
         */
        protected $class_loader,
        /**
         * Whether the container can be dumped.
         */
        protected $allow_dumping = true,
        $app_root = null
    )
    {
        if ($app_root === null) {
            $app_root = static::guess_application_root();
        }
        $this->root = $app_root;
    }
    /**
     * Determine the application root directory based on this file's location.
     *
     * @return string
     *   The application root.
     */
    protected static function guess_application_root(): string
    {
        // Determine the application root by:
        // - Removing the namespace directories from the path.
        // - Getting the path to the directory two levels up from the path
        //   determined in the previous step.
        return dirname(substr(__DIR__, 0, -strlen(__NAMESPACE__)), 2);
    }
    /**
     * Returns the appropriate site directory for a request.
     *
     * Once the kernel has been created DrupalKernelInterface::getSitePath() is
     * preferred since it gets the statically cached result of this method.
     *
     * Site directories contain all site specific code. This includes settings.php
     * for bootstrap level configuration, file configuration stores, public file
     * storage and site specific modules and themes.
     *
     * A file named sites.php must be present in the sites directory for
     * multisite. If it doesn't exist, then 'sites/default' will be used.
     *
     * Finds a matching site directory file by stripping the website's hostname
     * from left to right and pathname from right to left. By default, the
     * directory must contain a 'settings.php' file for it to match. If the
     * parameter $require_settings is set to FALSE, then a directory without a
     * 'settings.php' file will match as well. The first configuration file found
     * will be used and the remaining ones will be ignored. If no configuration
     * file is found, returns a default value 'sites/default'. See
     * default.settings.php for examples on how the URL is converted to a
     * directory.
     *
     * The sites.php file in the sites directory can define aliases in an
     * associative array named $sites. The array is written in the format
     * '<port>.<domain>.<path>' => 'directory'. As an example, to create a
     * directory alias for https://www.drupal.org:8080/my-site/test whose
     * configuration file is in sites/example.com, the array should be defined as:
     * @code
     * $sites = [
     *   '8080.www.drupal.org.my-site.test' => 'example.com',
     * ];
     * @endcode
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     * @param bool $require_settings
     *   Only directories with an existing settings.php file will be recognized.
     *   Defaults to TRUE. During initial installation, this is set to FALSE so
     *   that Drupal can detect a matching directory, then create a new
     *   settings.php file in it.
     * @param string $app_root
     *   (optional) The path to the application root as a string. If not supplied,
     *   the application root will be computed.
     *
     * @return string
     *   The path of the matching directory.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *   In case the host name in the request is invalid.
     *
     * @see \Drupal\Core\DrupalKernelInterface::getSitePath()
     * @see \Drupal\Core\DrupalKernelInterface::setSitePath()
     * @see default.settings.php
     * @see example.sites.php
     */
    public static function find_site_path(Request $request, $require_settings = true, $app_root = null): string
    {
        if (static::validate_hostname($request) === false) {
            throw new Bad_Request_Http_Exception();
        }
        if ($app_root === null) {
            $app_root = static::guess_application_root();
        }
        // Check for a test override.
        if ($test_prefix = drupal_valid_test_ua()) {
            $test_db = new Test_Database($test_prefix);
            return $test_db->get_test_site_path();
        }
        // Determine whether multi-site functionality is enabled. If not, return
        // the default directory.
        if (!is_file($app_root . '/sites/sites.php')) {
            return 'sites/default';
        }
        // Pre-populate host and script variables, then include sites.php which may
        // populate $sites with a site-directory mapping.
        $script_name = $request->server->get('SCRIPT_NAME');
        if (!$script_name) {
            $script_name = $request->server->get('SCRIPT_FILENAME');
        }
        $http_host = $request->get_http_host();
        $sites = [];
        include $app_root . '/sites/sites.php';
        // Construct an identifier from pieces of the (port plus) host plus script
        // path (excluding the filename). Loop over all possibilities starting from
        // most specific, then dropping pieces from the start of the port/hostname
        // while keeping the full path, then gradually dropping pieces from the end
        // of the path... until we find a directory corresponding to the identifier.
        $path_parts = explode('/', (string) $script_name);
        $host_parts = explode('.', implode('.', array_reverse(explode(':', rtrim($http_host, '.')))));
        for ($i = count($path_parts) - 1; $i > 0; $i--) {
            for ($j = count($host_parts); $j > 0; $j--) {
                // Assume the path has a leading slash, so the imploded path parts are
                // either a path identifier with leading dot, or an empty string.
                $site_id = implode('.', array_slice($host_parts, -$j)) . implode('.', array_slice($path_parts, 0, $i));
                // If the identifier is a key in $sites, check for a directory matching
                // the corresponding value. Otherwise, check for a directory matching
                // the identifier.
                if (isset($sites[$site_id]) && is_dir($app_root . '/sites/' . $sites[$site_id])) {
                    $site_id = $sites[$site_id];
                }
                if (is_file($app_root . '/sites/' . $site_id . '/settings.php') || !$require_settings && is_file($app_root . '/sites/' . $site_id)) {
                    return "sites/{$site_id}";
                }
            }
        }
        return 'sites/default';
    }
    /**
     * {@inheritdoc}
     */
    public function set_site_path($path): void
    {
        if ($this->booted && $path !== $this->site_path) {
            throw new \LogicException('Site path cannot be changed after calling boot()');
        }
        $this->site_path = $path;
    }
    /**
     * {@inheritdoc}
     */
    public function get_site_path()
    {
        return $this->site_path;
    }
    /**
     * {@inheritdoc}
     */
    public function get_app_root()
    {
        return $this->root;
    }
    /**
     * {@inheritdoc}
     */
    public function boot(): static
    {
        if ($this->booted) {
            return $this;
        }
        // Ensure that findSitePath is set.
        if (!$this->site_path) {
            throw new \Exception('Kernel does not have site path set before calling boot()');
        }
        // Initialize the FileCacheFactory component. We have to do it here instead
        // of in \Drupal\Component\FileCache\FileCacheFactory because we can not use
        // the Settings object in a component.
        $configuration = Settings::get('file_cache');
        // Provide a default configuration, if not set.
        if (!isset($configuration['default'])) {
            // @todo Use extension_loaded('apcu') for non-testbot
            //   https://www.drupal.org/node/2447753.
            if (function_exists('apcu_fetch')) {
                $configuration['default']['cache_backend_class'] = \Drupal\Component\File_Cache\Apcu_File_Cache_Backend::class;
            }
        }
        File_Cache_Factory::set_configuration($configuration);
        File_Cache_Factory::set_prefix(Settings::get_apcu_prefix('file_cache', $this->root));
        $this->bootstrap_container = new $this->bootstrap_container_class(Settings::get('bootstrap_container_definition', $this->default_bootstrap_container_definition));
        // Initialize the container.
        $this->initialize_container();
        // Add the APCu prefix to use to cache found/not-found classes.
        if (Settings::get('class_loader_auto_detect', true) && method_exists($this->class_loader, 'setApcuPrefix')) {
            // Vary the APCu key by which extensions are installed to allow
            // class_exists() checks to determine functionality.
            $installed_extensions = array_keys($this->container->get_parameter('container.modules') + $this->container->get_parameter('container.themes'));
            $id = 'class_loader:' . crc32(implode(':', $installed_extensions));
            $prefix = Settings::get_apcu_prefix($id, $this->root);
            $this->class_loader->set_apcu_prefix($prefix);
        }
        if ($this->container->has_parameter('moved_classes')) {
            $bc_class_loader = new Backwards_Compatibility_Class_Loader($this->container->get_parameter('moved_classes'));
            spl_autoload_register($bc_class_loader->load_class(...));
        }
        $this->booted = true;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
        if (false === $this->booted) {
            return;
        }
        $this->container->get('stream_wrapper_manager')->unregister();
        $this->booted = false;
        $this->config_storage = null;
        $this->container = null;
        $this->module_list = null;
        $this->module_data = [];
        $this->theme_list = null;
        $this->theme_extensions = [];
    }
    /**
     * {@inheritdoc}
     */
    public function get_container()
    {
        return $this->container;
    }
    /**
     * {@inheritdoc}
     */
    public function get_cached_container_definition()
    {
        $cache = $this->bootstrap_container->get('cache.container')->get($this->get_container_cache_key());
        if ($cache) {
            return $cache->data;
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function load_legacy_includes(): void
    {
        require_once $this->root . '/core/includes/common.inc';
        require_once $this->root . '/core/includes/module.inc';
        require_once $this->root . '/core/includes/theme.inc';
        require_once $this->root . '/core/includes/form.inc';
        require_once $this->root . '/core/includes/errors.inc';
    }
    /**
     * {@inheritdoc}
     */
    public function pre_handle(Request $request): void
    {
        // Sanitize the request.
        $request = Request_Sanitizer::sanitize($request, (array) Settings::get(Request_Sanitizer::SANITIZE_INPUT_SAFE_KEYS, []), (bool) Settings::get(Request_Sanitizer::SANITIZE_LOG, false));
        // Ensure that there is a session on every request.
        if (!$request->has_session()) {
            $this->initialize_ephemeral_session($request);
        }
        $this->load_legacy_includes();
        // Load all enabled modules.
        $this->container->get('module_handler')->load_all();
        // Register stream wrappers.
        $this->container->get('stream_wrapper_manager')->register();
        // Initialize legacy request globals.
        $this->initialize_request_globals($request);
        // Put the request on the stack. Main requests will be popped in
        // \Drupal\Core\DrupalKernel::terminate() and sub requests will be popped in
        // \Drupal\Core\StackMiddleware\KernelPreHandle::handle().
        $this->container->get('request_stack')->push($request);
        // Set the allowed protocols.
        Url_Helper::set_allowed_protocols($this->container->get_parameter('filter_protocols'));
        // Override of Symfony's MIME type guesser singleton.
        Mime_Type_Guesser::register_with_symfony_guesser($this->container);
        $this->prepared = true;
    }
    /**
     * {@inheritdoc}
     */
    public function discover_service_providers(): void
    {
        $this->service_yamls = ['app' => [], 'site' => []];
        $this->service_provider_classes = ['app' => [], 'site' => []];
        $this->service_yamls['app']['core'] = 'core/core.services.yml';
        $this->service_provider_classes['app']['core'] = \Drupal\Core\Core_Service_Provider::class;
        // Retrieve enabled modules and register their namespaces.
        if (!isset($this->module_list) || !isset($this->theme_list)) {
            $extensions = $this->get_extensions();
            // The module list is manipulated in the TestRunnerKernel, so we should
            // only set it if it is not set.
            if (!isset($this->module_list)) {
                // If core.extension configuration does not exist and we're not in the
                // installer itself, then we need to put the kernel into a pre-installer
                // mode. The container should not be dumped because Drupal is yet to be
                // installed. The installer service provider is registered to ensure
                // that cache and other automatically created tables are not created if
                // database settings are available. None of this is required when the
                // installer is running because the installer has its own kernel and
                // manages the addition of its own service providers.
                // @see install_begin_request()
                if ($extensions === false && !Installer_Kernel::installation_attempted()) {
                    $this->allow_dumping = false;
                    $this->container_needs_dumping = false;
                    $GLOBALS['conf']['container_service_providers']['InstallerServiceProvider'] = \Drupal\Core\Installer\Installer_Service_Provider::class;
                }
                $this->module_list = $extensions['module'] ?? [];
            }
            $this->theme_list = $extensions['theme'] ?? [];
        }
        $module_filenames = $this->get_extension_file_names($this->module_list, $this->module_data(...));
        $this->class_loader_add_multiple_psr4($this->get_extension_namespaces_psr4($module_filenames));
        $theme_filenames = $this->get_extension_file_names($this->theme_list, $this->theme_extensions(...));
        $this->class_loader_add_multiple_psr4($this->get_extension_namespaces_psr4($theme_filenames));
        // Load each module's serviceProvider class.
        foreach ($module_filenames as $module => $filename) {
            $camelized = Container_Builder::camelize($module);
            $name = "{$camelized}ServiceProvider";
            $class = "Drupal\\{$module}\\{$name}";
            if (class_exists($class)) {
                $this->service_provider_classes['app'][$module] = $class;
            }
            $filename = dirname((string) $filename) . "/{$module}.services.yml";
            if (is_file($filename)) {
                $this->service_yamls['app'][$module] = $filename;
            }
        }
        // Add site-specific service providers.
        if (!empty($GLOBALS['conf']['container_service_providers'])) {
            foreach ($GLOBALS['conf']['container_service_providers'] as $class) {
                if (is_string($class) && class_exists($class) || is_object($class) && ($class instanceof Service_Provider_Interface || $class instanceof Service_Modifier_Interface)) {
                    $this->service_provider_classes['site'][] = $class;
                }
            }
        }
        $this->add_service_files(Settings::get('container_yamls', []));
    }
    /**
     * {@inheritdoc}
     */
    public function get_service_providers($origin)
    {
        return $this->service_providers[$origin];
    }
    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->booted && $this->get_http_kernel() instanceof Terminable_Interface) {
            // Only run terminate() when essential services have been set up properly
            // by preHandle() before.
            if ($this->prepared === true) {
                $this->get_http_kernel()->terminate($request, $response);
            }
            // For destructable services, always call the destruct method if they were
            // initialized during the request. Destruction is not necessary if the
            // service was not used.
            foreach ($this->container->get_parameter('kernel.destructable_services') as $id) {
                if ($this->container->initialized($id)) {
                    $service = $this->container->get($id);
                    $service->destruct();
                }
            }
            // Pop the request added in \Drupal\Core\DrupalKernel::preHandle() from
            // request stack at the end of the execution cycle.
            if ($this->prepared === true) {
                $this->container->get('request_stack')->pop();
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        // Ensure sane PHP environment variables.
        static::boot_environment();
        try {
            if (!$this->booted) {
                $this->initialize_settings($request);
                $this->boot();
            }
            $response = $this->get_http_kernel()->handle($request, $type, $catch);
        } catch (\Exception $e) {
            if ($catch === false) {
                throw $e;
            }
            $response = $this->handle_exception($e, $request, $type);
        }
        // Adapt response headers to the current request.
        $response->prepare($request);
        return $response;
    }
    /**
     * Converts an exception into a response.
     *
     * @param \Exception $e
     *   An exception.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   A Request instance.
     * @param int $type
     *   The type of the request (one of HttpKernelInterface::MAIN_REQUEST or
     *   HttpKernelInterface::SUB_REQUEST)
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *   A Response instance
     *
     * @throws \Exception
     *   If the passed in exception cannot be turned into a response.
     */
    protected function handle_exception(\Exception $e, $request, $type): \Symfony\Component\Http_Foundation\Redirect_Response|\Symfony\Component\Http_Foundation\Response
    {
        if ($this->should_redirect_to_installer($e, $this->container ? $this->container->get('database') : null)) {
            return new Redirect_Response($request->get_base_path() . '/core/install.php', 302, ['Cache-Control' => 'no-cache']);
        }
        if ($e instanceof Http_Exception_Interface) {
            $response = new Response($e->get_message(), $e->get_status_code());
            $response->headers->add($e->get_headers());
            $response->headers->set('Content-Type', 'text/plain');
            return $response;
        }
        throw $e;
    }
    /**
     * Returns module data on the filesystem.
     *
     * @param string $module
     *   The name of the module.
     *
     * @return \Drupal\Core\Extension\Extension|false
     *   Returns an Extension object if the module is found, FALSE otherwise.
     */
    protected function module_data($module)
    {
        if (!$this->module_data) {
            $this->set_extension_data();
        }
        return $this->module_data[$module] ?? false;
    }
    /**
     * Sets extension data to class properties using ExtensionDiscovery.
     *
     * This function is expensive to call as it scans the filesystem for
     * extensions. Use ::moduleData() and ::themeExtensions() instead.
     */
    private function set_extension_data(): void
    {
        // First, find profiles.
        $listing = new Extension_Discovery($this->root);
        $listing->set_profile_directories([]);
        $all_profiles = $listing->scan('profile');
        $profiles = array_intersect_key($all_profiles, $this->module_list);
        $profile_directories = array_map(fn(Extension $profile) => $profile->get_path(), $profiles);
        $listing->set_profile_directories($profile_directories);
        // Now find modules.
        $this->module_data = $profiles + $listing->scan('module');
        // Now find themes.
        $this->theme_extensions = $listing->scan('theme');
    }
    /**
     * Implements Drupal\Core\DrupalKernelInterface::updateModules().
     *
     * @todo Remove obsolete $module_list parameter. Only $module_filenames is
     *   needed.
     */
    public function update_modules(array $module_list, array $module_filenames = []): void
    {
        $pre_existing_module_namespaces = [];
        if ($this->booted && is_array($this->module_list)) {
            $pre_existing_module_namespaces = $this->get_extension_namespaces_psr4($this->get_extension_file_names($this->module_list, $this->module_data(...)));
        }
        $this->module_list = $module_list;
        foreach ($module_filenames as $name => $extension) {
            $this->module_data[$name] = $extension;
        }
        // If we haven't yet booted, we don't need to do anything: the new module
        // list will take effect when boot() is called. However we set a
        // flag that the container needs a rebuild, so that a potentially cached
        // container is not used. If we have already booted, then rebuild the
        // container in order to refresh the serviceProvider list and container.
        $this->container_needs_rebuild = true;
        if ($this->booted) {
            // We need to register any new namespaces to a new class loader because
            // the current class loader might have stored a negative result for a
            // class that is now available.
            // @see \Composer\Autoload\ClassLoader::findFile()
            $new_namespaces = array_diff_key($this->get_extension_namespaces_psr4($this->get_extension_file_names($this->module_list, $this->module_data(...))), $pre_existing_module_namespaces);
            if (!empty($new_namespaces)) {
                $additional_class_loader = new Class_Loader();
                $this->class_loader_add_multiple_psr4($new_namespaces, $additional_class_loader);
                $additional_class_loader->register();
            }
            $this->initialize_container();
        }
    }
    /**
     * Returns theme data on the filesystem.
     *
     * This allows us to update the container parameters and namespaces during
     * compile, theme install and theme uninstall. This ensures that the
     * container remains in sync before compiler passes.
     *
     * @param string $theme
     *   The name of the theme.
     *
     * @return \Drupal\Core\Extension\Extension|false
     *   Returns an Extension object if the theme is found, FALSE otherwise.
     */
    protected function theme_extensions($theme): Extension|false
    {
        if (!$this->theme_extensions) {
            $this->set_extension_data();
        }
        return $this->theme_extensions[$theme] ?? false;
    }
    /**
     * {@inheritdoc}
     */
    public function update_themes(array $register_themes = []): void
    {
        $pre_existing_theme_namespaces = [];
        if ($this->booted && isset($this->theme_list)) {
            $pre_existing_theme_namespaces = $this->get_extension_namespaces_psr4($this->get_extension_file_names($this->theme_list, $this->theme_extensions(...)));
        }
        $this->theme_list = $register_themes;
        foreach ($register_themes as $name => $extension) {
            $this->theme_extensions[$name] = $extension;
        }
        // If we haven't yet booted, we don't need to do anything: the new theme
        // list will take effect when boot() is called. However we set a
        // flag that the container needs a rebuild, so that a potentially cached
        // container is not used. If we have already booted, then rebuild the
        // container in order to refresh the serviceProvider list and container.
        $this->container_needs_rebuild = true;
        if ($this->booted) {
            // We need to register any new namespaces to a new class loader because
            // the current class loader might have stored a negative result for a
            // class that is now available.
            // @see \Composer\Autoload\ClassLoader::findFile()
            $new_namespaces = array_diff_key($this->get_extension_namespaces_psr4($this->get_extension_file_names($this->theme_list, $this->theme_extensions(...))), $pre_existing_theme_namespaces);
            if (!empty($new_namespaces)) {
                $additional_class_loader = new Class_Loader();
                $this->class_loader_add_multiple_psr4($new_namespaces, $additional_class_loader);
                $additional_class_loader->register();
            }
            $this->initialize_container();
        }
    }
    /**
     * Returns the container cache key based on the environment.
     *
     * The 'environment' consists of:
     * - The kernel environment string.
     * - A hash based on all the installed package versions.
     * - The deployment identifier from settings.php. This allows custom
     *   deployments to force a container rebuild.
     * - The operating system running PHP. This allows compiler passes to optimize
     *   services for different operating systems.
     * - The paths to any additional container YAMLs from settings.php.
     *
     * @return string
     *   The cache key used for the service container.
     */
    protected function get_container_cache_key(): string
    {
        $parts = ['service_container', $this->environment, class_exists(Drupal_Installed::class) ? Drupal_Installed::VERSIONS_HASH : \Drupal::VERSION, Settings::get('deployment_identifier'), PHP_OS, serialize(Settings::get('container_yamls'))];
        return implode(':', $parts);
    }
    /**
     * Returns the kernel parameters.
     *
     * @return array
     *   An associative array of kernel parameters
     */
    protected function get_kernel_parameters(): array
    {
        return ['kernel.environment' => $this->environment];
    }
    /**
     * Initializes the service container.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     *   An initialized container object.
     */
    protected function initialize_container()
    {
        $this->container_needs_dumping = false;
        $session_started = false;
        $all_messages = [];
        if (isset($this->container)) {
            // Save the id of the currently logged in user.
            if ($this->container->initialized('current_user')) {
                $current_user_id = $this->container->get('current_user')->id();
            }
            // After rebuilding the container some objects will have stale services.
            // Record a map of objects to service IDs prior to rebuilding the
            // container in order to ensure
            // \Drupal\Core\DependencyInjection\DependencySerializationTrait works as
            // expected.
            $this->container->get(Reverse_Container::class)->record_container();
            // If there is a session, close and save it.
            if ($this->container->initialized('session')) {
                $session = $this->container->get('session');
                if ($session->is_started()) {
                    $session_started = true;
                    $session->save();
                }
                unset($session);
            }
            $all_messages = $this->container->get('messenger')->all();
        }
        // If the module list hasn't already been set in updateModules and we are
        // not forcing a rebuild, then try and load the container from the cache.
        if (empty($this->module_list) && !$this->container_needs_rebuild) {
            $container_definition = $this->get_cached_container_definition();
        }
        // If there is no cached container definition, build a new container from
        // scratch.
        if (!isset($container_definition)) {
            $container = $this->compile_container();
            // Only dump the container if dumping is allowed. This is useful for
            // KernelTestBase, which never wants to use the real container, but always
            // the container builder.
            if ($this->allow_dumping) {
                $dumper = new $this->php_array_dumper_class($container);
                $container_definition = $dumper->get_array();
            }
        }
        // The container was rebuilt successfully.
        $this->container_needs_rebuild = false;
        // Only create a new class if we have a container definition.
        if (isset($container_definition)) {
            // Drupal provides two dynamic parameters to access specific paths that
            // are determined from the request.
            $container_definition['parameters']['app.root'] = $this->get_app_root();
            $container_definition['parameters']['site.path'] = $this->get_site_path();
            $class = Settings::get('container_base_class', \Drupal\Core\Dependency_Injection\Container::class);
            $container = new $class($container_definition);
        }
        $this->attach_synthetic($container);
        $this->container = $container;
        if ($session_started) {
            $this->container->get('session')->start();
        }
        // The request stack is preserved across container rebuilds. Re-inject the
        // new session into the main request if one was present before.
        if ($request_stack = $this->container->get('request_stack', Container_Interface::NULL_ON_INVALID_REFERENCE)) {
            if ($request = $request_stack->get_main_request()) {
                $subrequest = true;
                $request->set_session($this->container->get('session'));
            }
        }
        if (!empty($current_user_id)) {
            $this->container->get('current_user')->set_initial_account_id($current_user_id);
        }
        // Re-add messages.
        foreach ($all_messages as $type => $messages) {
            foreach ($messages as $message) {
                $this->container->get('messenger')->add_message($message, $type);
            }
        }
        \Drupal::set_container($this->container);
        // Allow other parts of the codebase to react on container initialization in
        // subrequest.
        if (!empty($subrequest)) {
            $this->container->get('event_dispatcher')->dispatch(new Event(), self::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED);
        }
        // If needs dumping flag was set, dump the container.
        if ($this->container_needs_dumping && !$this->cache_drupal_container($container_definition)) {
            $this->container->get('logger.factory')->get('DrupalKernel')->error('Container cannot be saved to cache.');
        }
        return $this->container;
    }
    /**
     * Setup a consistent PHP environment.
     *
     * This method sets PHP environment options we want to be sure are set
     * correctly for security or just saneness.
     *
     * @param string $app_root
     *   (optional) The path to the application root as a string. If not supplied,
     *   the application root will be computed.
     */
    public static function boot_environment($app_root = null): void
    {
        if (static::$is_environment_initialized) {
            return;
        }
        // Determine the application root if it's not supplied.
        if ($app_root === null) {
            $app_root = static::guess_application_root();
        }
        error_reporting(E_ALL);
        // Override PHP settings required for Drupal to work properly.
        // sites/default/default.settings.php contains more runtime settings.
        // The .htaccess file contains settings that cannot be changed at runtime.
        if (PHP_SAPI !== 'cli') {
            // Use session cookies, not transparent sessions that puts the session id
            // in the query string.
            ini_set('session.use_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            // Don't send HTTP headers using PHP's session handler.
            // Send an empty string to disable the cache limiter.
            ini_set('session.cache_limiter', '');
            // Use httponly session cookies.
            ini_set('session.cookie_httponly', '1');
        }
        // Set sane locale settings, to ensure consistent string, dates, times and
        // numbers handling.
        setlocale(LC_ALL, 'C.UTF-8', 'C');
        // Set appropriate configuration for multi-byte strings.
        mb_internal_encoding('utf-8');
        mb_language('uni');
        // Indicate that code is operating in a test child site.
        if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
            if ($test_prefix = drupal_valid_test_ua()) {
                $test_db = new Test_Database($test_prefix);
                // Only code that interfaces directly with tests should rely on this
                // constant; e.g., the error/exception handler conditionally adds
                // further error information into HTTP response headers that are
                // consumed by the internal browser.
                define('DRUPAL_TEST_IN_CHILD_SITE', true);
                // Log fatal errors to the test site directory.
                ini_set('log_errors', 1);
                ini_set('error_log', $app_root . '/' . $test_db->get_test_site_path() . '/error.log');
                // Ensure that a rewritten settings.php is used if OPcache is on.
                ini_set('opcache.validate_timestamps', 'on');
                ini_set('opcache.revalidate_freq', 0);
            } else {
                // Ensure that no other code defines this.
                define('DRUPAL_TEST_IN_CHILD_SITE', false);
            }
        }
        // Set the Drupal custom error handler.
        set_error_handler(_drupal_error_handler(...));
        set_exception_handler(_drupal_exception_handler(...));
        static::$is_environment_initialized = true;
    }
    /**
     * Locate site path and initialize settings singleton.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *   In case the host name in the request is not trusted.
     */
    protected function initialize_settings(Request $request)
    {
        $site_path = static::find_site_path($request);
        $this->set_site_path($site_path);
        Settings::initialize($this->root, $site_path, $this->class_loader);
        // Initialize our list of trusted HTTP Host headers to protect against
        // header attacks.
        $host_patterns = Settings::get('trusted_host_patterns', []);
        if (PHP_SAPI !== 'cli' && !empty($host_patterns)) {
            if (static::setup_trusted_hosts($request, $host_patterns) === false) {
                throw new Bad_Request_Http_Exception('The provided host name is not valid for this server.');
            }
        }
    }
    /**
     * Bootstraps the legacy global request variables.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     *
     * @todo D8: Eliminate this entirely in favor of Request object.
     */
    protected function initialize_request_globals(Request $request)
    {
        global $base_url;
        // Set and derived from $base_url by this function.
        global $base_path, $base_root;
        global $base_secure_url, $base_insecure_url;
        // Create base URL.
        $base_root = $request->get_scheme_and_http_host();
        $base_url = $base_root;
        // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
        // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
        if ($dir = rtrim(dirname((string) $request->server->get('SCRIPT_NAME')), '\/')) {
            // Remove "core" directory if present, allowing install.php, rebuild.php,
            // and others to auto-detect a base path.
            $core_position = strrpos($dir, '/core');
            if ($core_position !== false && strlen($dir) - 5 == $core_position) {
                $base_path = substr($dir, 0, $core_position);
            } else {
                $base_path = $dir;
            }
            $base_url .= $base_path;
            $base_path .= '/';
        } else {
            $base_path = '/';
        }
        $base_secure_url = str_replace('http://', 'https://', $base_url);
        $base_insecure_url = str_replace('https://', 'http://', $base_url);
    }
    /**
     * Returns service instances to persist from an old container to a new one.
     * @return mixed[]
     */
    protected function get_services_to_persist(Container_Interface $container): array
    {
        $persist = [];
        foreach ($container->get_parameter('persist_ids') as $id) {
            // It's pointless to persist services not yet initialized.
            if ($container->initialized($id)) {
                $persist[$id] = $container->get($id);
            }
        }
        return $persist;
    }
    /**
     * Moves persistent service instances into a new container.
     */
    protected function persist_services(Container_Interface $container, array $persist)
    {
        foreach ($persist as $id => $object) {
            // Do not override services already set() on the new container, for
            // example 'service_container', always replace the request stack.
            if (!$container->initialized($id) || $id === 'request_stack') {
                $container->set($id, $object);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function rebuild_container()
    {
        // Empty module properties and for them to be reloaded from scratch.
        $this->module_list = null;
        $this->module_data = [];
        $this->theme_list = null;
        $this->theme_extensions = [];
        $this->container_needs_rebuild = true;
        $container = $this->initialize_container();
        // ThemeManager::render() fails without this. Normally ::preHandle() has
        // a ->loadAll() call.
        $container->get('module_handler')->load_all();
        return $container;
    }
    /**
     * {@inheritdoc}
     */
    public function reset_container(): Container_Interface
    {
        $session_started = false;
        $subrequest = false;
        $reload_module_handler = false;
        // Save the id of the currently logged in user.
        if ($this->container->initialized('current_user')) {
            $current_user_id = $this->container->get('current_user')->id();
        }
        if ($this->container->initialized('module_handler') && $this->container->get('module_handler')->is_loaded()) {
            $reload_module_handler = true;
        }
        // After rebuilding the container some objects will have stale services.
        // Record a map of objects to service IDs prior to rebuilding the
        // container in order to ensure
        // \Drupal\Core\DependencyInjection\DependencySerializationTrait works as
        // expected.
        $this->container->get(Reverse_Container::class)->record_container();
        // If there is a session, close and save it.
        if ($this->container->initialized('session')) {
            $session = $this->container->get('session');
            if ($session->is_started()) {
                $session_started = true;
                $session->save();
            }
            unset($session);
        }
        $all_messages = $this->container->get('messenger')->all();
        $persist = $this->get_services_to_persist($this->container);
        $this->container->reset();
        $this->persist_services($this->container, $persist);
        $this->container->set('kernel', $this);
        // Set the class loader which was registered as a synthetic service.
        $this->container->set('class_loader', $this->class_loader);
        if ($reload_module_handler) {
            $this->container->get('module_handler')->reload();
        }
        if ($session_started) {
            $this->container->get('session')->start();
        }
        // The request stack is preserved across container rebuilds. Re-inject the
        // new session into the main request if one was present before.
        if ($request_stack = $this->container->get('request_stack', Container_Interface::NULL_ON_INVALID_REFERENCE)) {
            if ($request = $request_stack->get_main_request()) {
                $subrequest = true;
                $request->set_session($this->container->get('session'));
            }
        }
        if (!empty($current_user_id)) {
            $this->container->get('current_user')->set_initial_account_id($current_user_id);
        }
        // Re-add messages.
        foreach ($all_messages as $type => $messages) {
            foreach ($messages as $message) {
                $this->container->get('messenger')->add_message($message, $type);
            }
        }
        // Allow other parts of the codebase to react on container reset in
        // subrequest.
        if (!empty($subrequest)) {
            $this->container->get('event_dispatcher')->dispatch(new Event(), self::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED);
        }
        return $this->container;
    }
    /**
     * {@inheritdoc}
     */
    public function invalidate_container(): void
    {
        // An invalidated container needs a rebuild.
        $this->container_needs_rebuild = true;
        // If we have not yet booted, settings or bootstrap services might not yet
        // be available. In that case the container will not be loaded from cache
        // due to the above setting when the Kernel is booted.
        if (!$this->booted) {
            return;
        }
        // Also remove the container definition from the cache backend.
        $this->bootstrap_container->get('cache.container')->delete_all();
    }
    /**
     * Attach synthetic values on to kernel.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     *   Container object.
     *
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     *   The container object with the kernel and the class loader added.
     */
    protected function attach_synthetic(Container_Interface $container): Container_Interface
    {
        $persist = [];
        if (isset($this->container)) {
            $persist = $this->get_services_to_persist($this->container);
        }
        $this->persist_services($container, $persist);
        // All namespaces must be registered before we attempt to use any service
        // from the container.
        $this->class_loader_add_multiple_psr4($container->get_parameter('container.namespaces'));
        $container->set('kernel', $this);
        // Set the class loader which was registered as a synthetic service.
        $container->set('class_loader', $this->class_loader);
        return $container;
    }
    /**
     * Compiles a new service container.
     *
     * @return \Drupal\Core\DependencyInjection\ContainerBuilder
     *   The compiled service container
     */
    protected function compile_container()
    {
        // We are forcing a container build so it is reasonable to assume that the
        // calling method knows something about the system has changed requiring the
        // container to be dumped to the filesystem.
        if ($this->allow_dumping) {
            $this->container_needs_dumping = true;
        }
        $this->initialize_service_providers();
        $container = $this->get_container_builder();
        $container->set('kernel', $this);
        $container->set_parameter('container.modules', $this->get_extensions_parameter($this->module_list, $this->module_data(...)));
        $container->set_parameter('container.themes', $this->get_extensions_parameter($this->theme_list ?? [], $this->theme_extensions(...)));
        $container->set_parameter('install_profile', $this->get_install_profile());
        // Get a list of namespaces and put it onto the container.
        $namespaces = $this->get_extension_namespaces_psr4($this->get_extension_file_names($this->module_list, $this->module_data(...)));
        $namespaces += $this->get_extension_namespaces_psr4($this->get_extension_file_names($this->theme_list ?? [], $this->theme_extensions(...)));
        // Add all components in \Drupal\Core and \Drupal\Component that have one or
        // more of Element, Entity and Plugin directories.
        foreach (['Core', 'Component'] as $parent_directory) {
            $path = 'core/lib/Drupal/' . $parent_directory;
            $parent_namespace = 'Drupal\\' . $parent_directory;
            foreach (new \Directory_Iterator($this->root . '/' . $path) as $component) {
                /** @var \DirectoryIterator $component */
                $pathname = $component->get_pathname();
                if (!$component->is_dot() && $component->is_dir() && (is_dir($pathname . '/Plugin') || is_dir($pathname . '/Entity') || is_dir($pathname . '/Element'))) {
                    $namespaces[$parent_namespace . '\\' . $component->get_filename()] = $path . '/' . $component->get_filename();
                }
            }
        }
        $container->set_parameter('container.namespaces', $namespaces);
        // Store the default language values on the container. This is so that the
        // default language can be configured using the configuration factory. This
        // avoids the circular dependencies that would created by
        // \Drupal\language\LanguageServiceProvider::alter() and allows the default
        // language to not be English in the installer.
        $default_language_values = Language::$default_values;
        if ($system = $this->get_config_storage()->read('system.site')) {
            if ($default_language_values['id'] != $system['langcode']) {
                $default_language_values = ['id' => $system['langcode']];
            }
        }
        $container->set_parameter('language.default_values', $default_language_values);
        // Register synthetic services.
        $container->register('class_loader')->set_synthetic(true);
        $container->register('kernel', 'Symfony\Component\HttpKernel\KernelInterface')->set_synthetic(true);
        $container->register('service_container', 'Symfony\Component\DependencyInjection\ContainerInterface')->set_synthetic(true);
        // Register aliases of synthetic services for autowiring.
        $container->set_alias(Drupal_Kernel_Interface::class, 'kernel');
        $container->set_alias(Container_Interface::class, 'service_container');
        // Register application services.
        $yaml_loader = new Yaml_File_Loader($container);
        foreach ($this->service_yamls['app'] as $filename) {
            $yaml_loader->load($filename);
        }
        foreach ($this->service_providers['app'] as $provider) {
            if ($provider instanceof Service_Provider_Interface) {
                $provider->register($container);
            }
        }
        // Register site-specific service overrides.
        foreach ($this->service_yamls['site'] as $filename) {
            $yaml_loader->load($filename);
        }
        foreach ($this->service_providers['site'] as $provider) {
            if ($provider instanceof Service_Provider_Interface) {
                $provider->register($container);
            }
        }
        // Identify all services whose instances should be persisted when rebuilding
        // the container during the lifetime of the kernel (e.g., during a kernel
        // reboot). Include synthetic services, because by definition, they cannot
        // be automatically re-instantiated. Also include services tagged to
        // persist.
        $persist_ids = [];
        foreach ($container->get_definitions() as $id => $definition) {
            // It does not make sense to persist the container itself, exclude it.
            if ($id !== 'service_container' && ($definition->is_synthetic() || $definition->get_tag('persist'))) {
                $persist_ids[] = $id;
            }
        }
        $container->set_parameter('persist_ids', $persist_ids);
        $container->set_parameter('app.root', $this->get_app_root());
        $container->set_parameter('site.path', $this->get_site_path());
        $container->compile();
        return $container;
    }
    /**
     * Registers all service providers to the kernel.
     *
     * @throws \LogicException
     */
    protected function initialize_service_providers()
    {
        $this->discover_service_providers();
        $this->service_providers = ['app' => [], 'site' => []];
        foreach ($this->service_provider_classes as $origin => $classes) {
            foreach ($classes as $name => $class) {
                if (!is_object($class)) {
                    $this->service_providers[$origin][$name] = new $class();
                } else {
                    $this->service_providers[$origin][$name] = $class;
                }
            }
        }
    }
    /**
     * Gets a new ContainerBuilder instance used to build the service container.
     *
     * @return \Drupal\Core\DependencyInjection\ContainerBuilder
     *   The Drupal dependency injection container builder.
     */
    protected function get_container_builder(): \Drupal\Core\Dependency_Injection\Container_Builder
    {
        return new Container_Builder(new Parameter_Bag($this->get_kernel_parameters()));
    }
    /**
     * Stores the container definition in a cache.
     *
     * @param array $container_definition
     *   The container definition to cache.
     *
     * @return bool
     *   TRUE if the container was successfully cached.
     */
    protected function cache_drupal_container(array $container_definition)
    {
        $saved = true;
        try {
            $this->bootstrap_container->get('cache.container')->set($this->get_container_cache_key(), $container_definition);
        } catch (\Exception) {
            // There is no way to get from the Cache API if the cache set was
            // successful or not, hence an Exception is caught and the caller informed
            // about the error condition.
            $saved = false;
        }
        return $saved;
    }
    /**
     * Gets a http kernel from the container.
     *
     * @return \Symfony\Component\HttpKernel\HttpKernelInterface
     *   The Symfony HTTP kernel service.
     */
    protected function get_http_kernel()
    {
        return $this->container->get('http_kernel');
    }
    /**
     * Gets the active configuration storage to use during building the container.
     *
     * @return \Drupal\Core\Config\StorageInterface
     *   The configuration storage.
     */
    protected function get_config_storage()
    {
        if (!isset($this->config_storage)) {
            // The active configuration storage may not exist yet; e.g., in the early
            // installer so if an exception is thrown use a NullStorage.
            try {
                $this->config_storage = Bootstrap_Config_Storage_Factory::get($this->class_loader);
            } catch (\Exception) {
                $this->config_storage = new Null_Storage();
            }
        }
        return $this->config_storage;
    }
    /**
     * Returns an array of Extension class parameters for all enabled modules.
     *
     * @return array
     *   An associated array of module class parameters, keyed by module name, for
     *   all enabled modules.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   getExtensionsParameter() instead.
     *
     * @see https://www.drupal.org/node/3551652
     */
    protected function get_modules_parameter(): array
    {
        @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use getExtensionsParameter() instead. See https://www.drupal.org/node/3551652', E_USER_DEPRECATED);
        return $this->get_extensions_parameter($this->module_list, $this->module_data(...));
    }
    /**
     * Returns an array of Extension class parameters for all enabled extensions.
     *
     * @param array $extension_list
     *   The list of extensions to return filenames for.
     * @param callable $get_data
     *   The method to get data for the extension type.
     *
     * @return array
     *   An associated array of extension class parameters, keyed by extension
     *   name, for all enabled themes.
     */
    protected function get_extensions_parameter(array $extension_list, callable $get_data): array
    {
        $extensions = [];
        foreach ($extension_list as $extension => $weight) {
            if ($data = $get_data($extension)) {
                $extensions[$extension] = ['type' => $data->get_type(), 'pathname' => $data->get_pathname(), 'filename' => $data->get_extension_filename()];
            }
        }
        return $extensions;
    }
    /**
     * Gets the filenames for each enabled module.
     *
     * @return array
     *   Array where each key is a module name, and each value is a path to the
     *   respective *.info.yml file.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   getExtensionFileNames() instead.
     *
     * @see https://www.drupal.org/node/3551652
     */
    protected function get_module_file_names()
    {
        @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use getExtensionFileNames() instead. See https://www.drupal.org/node/3551652', E_USER_DEPRECATED);
        return $this->get_extension_file_names($this->module_list, $this->module_data(...));
    }
    /**
     * Gets the filenames for each enabled extension.
     *
     * @param array $extension_list
     *   The list of extensions to return filenames for.
     * @param callable $get_data
     *   The method to get data for the extension type.
     *
     * @return array
     *   Array where each key is a theme name, and each value is a path to the
     *   respective *.info.yml file.
     */
    protected function get_extension_file_names(array $extension_list, callable $get_data): array
    {
        $filenames = [];
        foreach ($extension_list as $extension => $weight) {
            if ($data = $get_data($extension)) {
                $filenames[$extension] = $data->get_pathname();
            }
        }
        return $filenames;
    }
    /**
     * Gets the PSR-4 base directories for module namespaces.
     *
     * @param string[] $module_file_names
     *   Array where each key is a module name, and each value is a path to
     *   the respective *.info.yml file.
     *
     * @return string[]
     *   Array where each key is a module namespace like 'Drupal\system', and
     *   each value is the PSR-4 base directory associated with the module
     *   namespace.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   getExtensionNamespacesPsr4() instead.
     *
     * @see https://www.drupal.org/node/3551652
     */
    protected function get_module_namespaces_psr4(array $module_file_names): array
    {
        @trigger_error(__FUNCTION__ . '() is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use getExtensionNamespacesPsr4() instead. See https://www.drupal.org/node/3551652', E_USER_DEPRECATED);
        return $this->get_extension_namespaces_psr4($module_file_names);
    }
    /**
     * Gets the PSR-4 base directories for extension namespaces.
     *
     * @param string[] $extension_file_names
     *   Array where each key is an extension name, and each value is a path to
     *   the respective *.info.yml file.
     *
     * @return string[]
     *   Array where each key is an extension namespace like 'Drupal\system', and
     *   each value is the PSR-4 base directory associated with the extension
     *   namespace.
     */
    protected function get_extension_namespaces_psr4(array $extension_file_names): array
    {
        $namespaces = [];
        foreach ($extension_file_names as $extension => $filename) {
            $namespaces["Drupal\\{$extension}"] = dirname($filename) . '/src';
        }
        return $namespaces;
    }
    /**
     * Registers a list of namespaces with PSR-4 directories for class loading.
     *
     * @param array $namespaces
     *   Array where each key is a namespace like 'Drupal\system', and each value
     *   is either a PSR-4 base directory, or an array of PSR-4 base directories
     *   associated with this namespace.
     * @param object $class_loader
     *   The class loader. Normally \Composer\Autoload\ClassLoader, as included by
     *   the front controller, but may also be decorated.
     */
    protected function class_loader_add_multiple_psr4(array $namespaces = [], $class_loader = null)
    {
        if ($class_loader === null) {
            $class_loader = $this->class_loader;
        }
        foreach ($namespaces as $prefix => $paths) {
            if (is_array($paths)) {
                foreach ($paths as $key => $value) {
                    $paths[$key] = $this->root . '/' . $value;
                }
            } elseif (is_string($paths)) {
                $paths = $this->root . '/' . $paths;
            }
            $class_loader->add_psr4($prefix . '\\', $paths);
        }
    }
    /**
     * Validates a hostname length.
     *
     * @param string $host
     *   A hostname.
     *
     * @return bool
     *   TRUE if the length is appropriate, or FALSE otherwise.
     */
    protected static function validate_hostname_length($host): bool
    {
        // Limit the length of the host name to 1000 bytes to prevent DoS attacks
        // with long host names.
        return strlen($host) <= 1000 && substr_count($host, '.') <= 100 && substr_count($host, ':') <= 100;
    }
    /**
     * Validates the hostname supplied from the HTTP request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     *
     * @return bool
     *   TRUE if the hostname is valid, or FALSE otherwise.
     */
    public static function validate_hostname(Request $request): bool
    {
        // $request->getHost() can throw an UnexpectedValueException if it
        // detects a bad hostname, but it does not validate the length.
        try {
            $http_host = $request->get_host();
        } catch (\UnexpectedValueException) {
            return false;
        }
        if (static::validate_hostname_length($http_host) === false) {
            return false;
        }
        return true;
    }
    /**
     * Sets up the lists of trusted HTTP Host headers.
     *
     * Since the HTTP Host header can be set by the user making the request, it
     * is possible to create an attack vectors against a site by overriding this.
     * Symfony provides a mechanism for creating a list of trusted Host values.
     *
     * Host patterns (as regular expressions) can be configured through
     * settings.php for multisite installations, sites using ServerAlias without
     * canonical redirection, or configurations where the site responds to default
     * requests. For example,
     *
     * @code
     * $settings['trusted_host_patterns'] = [
     *   '^example\.com$',
     *   '^*.example\.com$',
     * ];
     * @endcode
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     * @param array $host_patterns
     *   The array of trusted host patterns.
     *
     * @return bool
     *   TRUE if the Host header is trusted, FALSE otherwise.
     *
     * @see https://www.drupal.org/docs/installing-drupal/trusted-host-settings
     * @see \Drupal\Core\Http\TrustedHostsRequestFactory
     */
    protected static function setup_trusted_hosts(Request $request, $host_patterns): bool
    {
        Request::set_trusted_hosts($host_patterns);
        // Get the host, which will validate the current request.
        try {
            $host = $request->get_host();
            // Fake requests created through Request::create() without passing in the
            // server variables from the main request have a default host of
            // 'localhost'. If 'localhost' does not match any of the trusted host
            // patterns these fake requests would fail the host verification. Instead,
            // TrustedHostsRequestFactory makes sure to pass in the server variables
            // from the main request.
            $request_factory = new Trusted_Hosts_Request_Factory($host);
            Request::set_factory($request_factory->create_request(...)(...));
        } catch (\UnexpectedValueException) {
            return false;
        }
        return true;
    }
    /**
     * Add service files.
     *
     * @param string[] $service_yamls
     *   A list of service files.
     */
    protected function add_service_files(array $service_yamls)
    {
        $this->service_yamls['site'] = array_filter($service_yamls, is_file(...));
    }
    /**
     * Gets the active install profile.
     *
     * @return string|false|null
     *   The name of the active install profile or distribution, FALSE if there is
     *   no install profile or NULL if Drupal is being installed.
     */
    protected function get_install_profile()
    {
        $config = $this->get_extensions();
        if (is_array($config) && !array_key_exists('profile', $config)) {
            return false;
        }
        return $config['profile'] ?? null;
    }
    /**
     * Initializes a session backed by in-memory store and puts it on the request.
     *
     * A simple in-memory store is sufficient for command line tools and tests.
     * Web requests will be processed by the session middleware where the mock
     * session is replaced by a session object backed with persistent storage and
     * a real session handler.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request.
     *
     * @see \Drupal\Core\StackMiddleware\Session::handle()
     */
    protected function initialize_ephemeral_session(Request $request): void
    {
        $session = new Session(new Mock_Array_Session_Storage());
        $session->start();
        $request->set_session($session);
    }
    /**
     * Get the core.extension config object.
     *
     * @return array|false
     *   The core.extension config object if it exists or FALSE.
     */
    protected function get_extensions(): array|false
    {
        return $this->get_config_storage()->read('core.extension');
    }
}