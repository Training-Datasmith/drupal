<?php

declare (strict_types=1);
namespace Drupal\Core\Controller;

use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\String_Translation\Translation_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver_Interface;
use Symfony\Component\Routing\Route;
/**
 * Provides the default implementation of the title resolver interface.
 */
class Title_Resolver implements Title_Resolver_Interface
{
    use String_Translation_Trait;
    /**
     * The argument resolver.
     *
     * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
     */
    protected $argument_resolver;
    /**
     * Constructs a TitleResolver instance.
     *
     * @param \Drupal\Core\Controller\ControllerResolverInterface $controllerResolver
     *   The controller resolver.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The translation manager.
     * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
     *   The argument resolver.
     */
    public function __construct(protected \Drupal\Core\Controller\Controller_Resolver_Interface $controller_resolver, Translation_Interface $string_translation, Argument_Resolver_Interface $argument_resolver)
    {
        $this->string_translation = $string_translation;
        $this->argument_resolver = $argument_resolver;
    }
    /**
     * {@inheritdoc}
     */
    public function get_title(Request $request, Route $route)
    {
        $route_title = null;
        // A dynamic title takes priority. Route::getDefault() returns NULL if the
        // named default is not set.  By testing the value directly, we also avoid
        // trying to use empty values.
        if ($callback = $route->get_default('_title_callback')) {
            $callable = $this->controller_resolver->get_controller_from_definition($callback);
            $arguments = $this->argument_resolver->get_arguments($request, $callable);
            $route_title = call_user_func_array($callable, $arguments);
        } elseif ($route->has_default('_title') && strlen($route->get_default('_title')) > 0) {
            $title = $route->get_default('_title');
            $options = [];
            if ($route->has_default('_title_context')) {
                $options['context'] = $route->get_default('_title_context');
            }
            $args = [];
            if ($route->has_default('_title_arguments')) {
                $args = (array) $route->get_default('_title_arguments');
            }
            if ($raw_parameters = $request->attributes->get('_raw_variables')) {
                foreach ($raw_parameters->all() as $key => $value) {
                    if (is_scalar($value)) {
                        $args['@' . $key] = $value;
                        $args['%' . $key] = $value;
                    }
                }
            }
            // Fall back to a static string from the route.
            // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
            $route_title = $this->t($title, $args, $options);
        }
        // Empty titles should return a NULL value as this is same result as title
        // not being set.
        if ($route_title === '' || $route_title instanceof Translatable_Markup && $route_title->get_untranslated_string() === '') {
            return null;
        }
        return $route_title;
    }
}