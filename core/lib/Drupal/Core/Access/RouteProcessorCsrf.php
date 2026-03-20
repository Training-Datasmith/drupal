<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Render\Bubbleable_Metadata;
use Drupal\Core\Route_Processor\Outbound_Route_Processor_Interface;
use Drupal\Core\Security\Trusted_Callback_Interface;
use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Routing\Route;
/**
 * Processes the outbound route to handle the CSRF token.
 */
class Route_Processor_Csrf implements Outbound_Route_Processor_Interface, Trusted_Callback_Interface
{
    use Route_Path_Generation_Trait;
    /**
     * Constructs a RouteProcessorCsrf object.
     *
     * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
     *   The CSRF token generator.
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     *   The request stack.
     */
    public function __construct(protected Csrf_Token_Generator $csrf_token, protected Request_Stack $request_stack)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function process_outbound($route_name, Route $route, array &$parameters, ?Bubbleable_Metadata $bubbleable_metadata = null): void
    {
        if ($route->has_requirement('_csrf_token')) {
            $path = $this->generate_route_path($route, $parameters);
            // Adding this to the parameters means it will get merged into the query
            // string when the route is compiled.
            if (!$bubbleable_metadata || $this->request_stack->get_current_request()->get_request_format() !== 'html') {
                $parameters['token'] = $this->csrf_token->get($path);
            } else {
                // Generate a placeholder and a render array to replace it.
                $placeholder = Crypt::hash_base64($path);
                $placeholder_render_array = ['#lazy_builder' => ['route_processor_csrf:renderPlaceholderCsrfToken', [$path]]];
                // Instead of setting an actual CSRF token as the query string, we set
                // the placeholder, which will be replaced at the very last moment. This
                // ensures links with CSRF tokens don't break cacheability.
                $parameters['token'] = $placeholder;
                $bubbleable_metadata->add_attachments(['placeholders' => [$placeholder => $placeholder_render_array]]);
            }
        }
    }
    /**
     * Render API callback: Adds a CSRF token for the given path to the markup.
     *
     * This function is assigned as a #lazy_builder callback.
     *
     * @param string $path
     *   The path to get a CSRF token for.
     *
     * @return array
     *   A renderable array representing the CSRF token.
     */
    public function render_placeholder_csrf_token($path): array
    {
        return [
            '#markup' => $this->csrf_token->get($path),
            // Tokens are per session.
            '#cache' => ['contexts' => ['session']],
        ];
    }
    /**
     * {@inheritdoc}
     */
    public static function trusted_callbacks(): array
    {
        return ['renderPlaceholderCsrfToken'];
    }
}