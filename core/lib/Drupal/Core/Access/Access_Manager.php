<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Component\Utility\Arguments_Resolver_Interface;
use Drupal\Core\Param_Converter\Param_Not_Converted_Exception;
use Drupal\Core\Routing\Route_Match;
use Drupal\Core\Routing\Route_Match_Interface;
use Drupal\Core\Routing\Route_Object_Interface;
use Drupal\Core\Session\Account_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Routing\Exception\Route_Not_Found_Exception;
/**
 * Attaches access check services to routes and runs them on request.
 *
 * @see \Drupal\Tests\Core\Access\AccessManagerTest
 */
class Access_Manager implements Access_Manager_Interface
{
    /**
     * Constructs an AccessManager instance.
     *
     * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
     *   The route provider.
     * @param \Drupal\Core\ParamConverter\ParamConverterManagerInterface $paramConverterManager
     *   The param converter manager.
     * @param \Drupal\Core\Access\AccessArgumentsResolverFactoryInterface $argumentsResolverFactory
     *   The access arguments resolver.
     * @param \Drupal\Core\Session\AccountInterface $currentUser
     *   The current user.
     * @param CheckProviderInterface $checkProvider
     *   The check access provider.
     */
    public function __construct(protected \Drupal\Core\Routing\Route_Provider_Interface $route_provider, protected \Drupal\Core\Param_Converter\Param_Converter_Manager_Interface $param_converter_manager, protected \Drupal\Core\Access\Access_Arguments_Resolver_Factory_Interface $arguments_resolver_factory, protected \Drupal\Core\Session\Account_Interface $current_user, protected \Drupal\Core\Access\Check_Provider_Interface $check_provider)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function check_named_route($route_name, array $parameters = [], ?Account_Interface $account = null, $return_as_object = false)
    {
        try {
            $route = $this->route_provider->get_route_by_name($route_name);
            // ParamConverterManager relies on the route name and object being
            // available from the parameters array.
            $parameters[Route_Object_Interface::ROUTE_NAME] = $route_name;
            $parameters[Route_Object_Interface::ROUTE_OBJECT] = $route;
            $upcasted_parameters = $this->param_converter_manager->convert($parameters + $route->get_defaults());
            $route_match = new Route_Match($route_name, $route, $upcasted_parameters, $parameters);
            return $this->check($route_match, $account, null, $return_as_object);
        } catch (Route_Not_Found_Exception) {
            // Cacheable until extensions change.
            $result = Access_Result::forbidden()->add_cache_tags(['config:core.extension']);
            return $return_as_object ? $result : $result->is_allowed();
        } catch (Param_Not_Converted_Exception) {
            // Uncacheable because conversion of the parameter may not have been
            // possible due to dynamic circumstances.
            $result = Access_Result::forbidden()->set_cache_max_age(0);
            return $return_as_object ? $result : $result->is_allowed();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function check_request(Request $request, ?Account_Interface $account = null, $return_as_object = false)
    {
        $route_match = Route_Match::create_from_request($request);
        return $this->check($route_match, $account, $request, $return_as_object);
    }
    /**
     * {@inheritdoc}
     */
    public function check(Route_Match_Interface $route_match, ?Account_Interface $account = null, ?Request $request = null, $return_as_object = false)
    {
        if (!isset($account)) {
            $account = $this->current_user;
        }
        $route = $route_match->get_route_object();
        $checks = $route->get_option('_access_checks') ?: [];
        // Filter out checks which require the incoming request.
        if (!isset($request)) {
            $checks = array_diff($checks, $this->check_provider->get_checks_need_request());
        }
        $result = Access_Result::neutral();
        if (!empty($checks)) {
            $arguments_resolver = $this->arguments_resolver_factory->get_arguments_resolver($route_match, $account, $request);
            $result = Access_Result::allowed();
            foreach ($checks as $service_id) {
                $result = $result->and_if($this->perform_check($service_id, $arguments_resolver));
            }
        }
        return $return_as_object ? $result : $result->is_allowed();
    }
    /**
     * Performs the specified access check.
     *
     * @param string $service_id
     *   The access check service ID to use.
     * @param \Drupal\Component\Utility\ArgumentsResolverInterface $arguments_resolver
     *   The parametrized arguments resolver instance.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     *
     * @throws \Drupal\Core\Access\AccessException
     *   Thrown when the access check returns an invalid value.
     */
    protected function perform_check($service_id, Arguments_Resolver_Interface $arguments_resolver)
    {
        $callable = $this->check_provider->load_check($service_id);
        $arguments = $arguments_resolver->get_arguments($callable);
        /** @var \Drupal\Core\Access\AccessResultInterface $service_access **/
        $service_access = call_user_func_array($callable, $arguments);
        if (!$service_access instanceof Access_Result_Interface) {
            throw new Access_Exception("Access error in {$service_id}. Access services must return an object that implements AccessResultInterface.");
        }
        return $service_access;
    }
}