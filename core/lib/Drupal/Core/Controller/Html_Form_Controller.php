<?php

declare (strict_types=1);
namespace Drupal\Core\Controller;

use Drupal\Core\Form\Form_Builder_Interface;
use Drupal\Core\Routing\Route_Match_Interface;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver_Interface;
/**
 * Wrapping controller for forms that serve as the main page body.
 */
class Html_Form_Controller extends Form_Controller
{
    /**
     * Constructs a new \Drupal\Core\Controller\HtmlFormController object.
     *
     * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
     *   The argument resolver.
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder.
     * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
     *   The class resolver.
     */
    public function __construct(Argument_Resolver_Interface $argument_resolver, Form_Builder_Interface $form_builder, protected \Drupal\Core\Dependency_Injection\Class_Resolver_Interface $class_resolver)
    {
        parent::__construct($argument_resolver, $form_builder);
    }
    /**
     * {@inheritdoc}
     */
    protected function get_form_argument(Route_Match_Interface $route_match)
    {
        return $route_match->get_route_object()->get_default('_form');
    }
    /**
     * {@inheritdoc}
     */
    protected function get_form_object(Route_Match_Interface $route_match, $form_arg)
    {
        return $this->class_resolver->get_instance_from_definition($form_arg);
    }
}