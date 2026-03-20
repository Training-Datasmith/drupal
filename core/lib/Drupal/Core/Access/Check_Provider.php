<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Routing\Access\Access_Interface;
use Psr\Container\Container_Interface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Route_Collection;
/**
 * Loads access checkers from the container.
 */
class Check_Provider implements Check_Provider_Interface
{
    /**
     * Array of registered access check service ids.
     *
     * @var array
     */
    protected $check_ids = [];
    /**
     * Array of access check objects keyed by service id.
     *
     * @var \Drupal\Core\Routing\Access\AccessInterface[]
     */
    protected $checks;
    /**
     * Array of access check method names keyed by service ID.
     *
     * @var array
     */
    protected $check_methods = [];
    /**
     * Array of access checks which only will be run on the incoming request.
     *
     * @var string[]
     */
    protected $checks_needs_request = [];
    /**
     * An array to map static requirement keys to service IDs.
     *
     * @var array
     */
    protected $static_requirement_map;
    /**
     * Constructs a CheckProvider object.
     *
     * @param array $dynamicRequirementMap
     *   An array to map dynamic requirement keys to service IDs.
     * @param \Psr\Container\ContainerInterface $container
     *   The check provider service locator.
     */
    public function __construct(protected array $dynamic_requirement_map, protected Container_Interface $container)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function add_check_service($service_id, $service_method, array $applies_checks = [], $needs_incoming_request = false): void
    {
        $this->check_ids[] = $service_id;
        $this->check_methods[$service_id] = $service_method;
        if ($needs_incoming_request) {
            $this->checks_needs_request[$service_id] = $service_id;
        }
        foreach ($applies_checks as $applies_check) {
            $this->static_requirement_map[$applies_check][] = $service_id;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_checks_need_request()
    {
        return $this->checks_needs_request;
    }
    /**
     * {@inheritdoc}
     */
    public function set_checks(Route_Collection $routes): void
    {
        foreach ($routes as $route) {
            if ($checks = $this->applies($route)) {
                $route->set_option('_access_checks', $checks);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function load_check($service_id): array
    {
        if (empty($this->checks[$service_id])) {
            if (!in_array($service_id, $this->check_ids)) {
                throw new \InvalidArgumentException(sprintf('No check has been registered for %s', $service_id));
            }
            $check = $this->container->get($service_id);
            if (!$check instanceof Access_Interface) {
                throw new Access_Exception('All access checks must implement AccessInterface.');
            }
            if (!is_callable([$check, $this->check_methods[$service_id]])) {
                throw new Access_Exception(sprintf('Access check method %s in service %s must be callable.', $this->check_methods[$service_id], $service_id));
            }
            $this->checks[$service_id] = $check;
        }
        return [$this->checks[$service_id], $this->check_methods[$service_id]];
    }
    /**
     * Determine which registered access checks apply to a route.
     *
     * @param \Symfony\Component\Routing\Route $route
     *   The route to get list of access checks for.
     *
     * @return array
     *   An array of service ids for the access checks that apply to passed
     *   route.
     */
    protected function applies(Route $route): array
    {
        $checks = [];
        // Iterate through map requirements from appliesTo() on access checkers.
        // Only iterate through all checkIds if this is not used.
        foreach ($route->get_requirements() as $key => $value) {
            if (isset($this->static_requirement_map[$key])) {
                foreach ($this->static_requirement_map[$key] as $service_id) {
                    $this->load_check($service_id);
                    $checks[] = $service_id;
                }
            }
        }
        // Finally, see if any dynamic access checkers apply.
        foreach ($this->dynamic_requirement_map as $service_id) {
            $this->load_check($service_id);
            if ($this->checks[$service_id]->applies($route)) {
                $checks[] = $service_id;
            }
        }
        return $checks;
    }
    /**
     * Compiles a mapping of requirement keys to access checker service IDs.
     */
    protected function load_dynamic_requirement_map()
    {
        if (!isset($this->dynamic_requirement_map)) {
            $this->dynamic_requirement_map = $this->container->get_parameter('dynamic_access_check_services');
        }
    }
}