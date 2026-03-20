<?php

declare (strict_types=1);
namespace Drupal\Core\Controller;

use Drupal\Core\Dependency_Injection\Dependency_Serialization_Trait;
use Drupal\Core\Form\Form_State;
use Drupal\Core\Routing\Route_Match_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver_Interface;
/**
 * Common base class for form interstitial controllers.
 */
abstract class Form_Controller
{
    use Dependency_Serialization_Trait;
    /**
     * The argument resolver.
     *
     * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
     */
    protected $argument_resolver;
    /**
     * Constructs a new \Drupal\Core\Controller\FormController object.
     *
     * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
     *   The argument resolver.
     * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
     *   The form builder.
     */
    public function __construct(Argument_Resolver_Interface $argument_resolver, protected \Drupal\Core\Form\Form_Builder_Interface $form_builder)
    {
        $this->argument_resolver = $argument_resolver;
    }
    /**
     * Invokes the form and returns the result.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     *
     * @return array
     *   The render array that results from invoking the controller.
     */
    public function get_content_result(Request $request, Route_Match_Interface $route_match)
    {
        $form_arg = $this->get_form_argument($route_match);
        $form_object = $this->get_form_object($route_match, $form_arg);
        // Add the form and form_state to trick the getArguments method of the
        // controller resolver.
        $form_state = new Form_State();
        $request->attributes->set('form', []);
        $request->attributes->set('form_state', $form_state);
        $args = $this->argument_resolver->get_arguments($request, $form_object->build_form(...));
        $request->attributes->remove('form');
        $request->attributes->remove('form_state');
        // Remove $form and $form_state from the arguments, and re-index them.
        unset($args[0], $args[1]);
        $form_state->add_build_info('args', array_values($args));
        return $this->form_builder->build_form($form_object, $form_state);
    }
    /**
     * Extracts the form argument string from a request.
     *
     * Depending on the type of form the argument string may be stored in a
     * different request attribute.
     *
     * One example of a route definition is given below.
     * @code
     *   defaults:
     *     _form: Drupal\example\Form\ExampleForm
     * @endcode
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match object from which to extract a form definition string.
     *
     * @return string
     *   The form definition string.
     */
    abstract protected function get_form_argument(Route_Match_Interface $route_match);
    /**
     * Returns the object used to build the form.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     * @param string $form_arg
     *   Either a class name or a service ID.
     *
     * @return \Drupal\Core\Form\FormInterface
     *   The form object to use.
     */
    abstract protected function get_form_object(Route_Match_Interface $route_match, $form_arg);
}