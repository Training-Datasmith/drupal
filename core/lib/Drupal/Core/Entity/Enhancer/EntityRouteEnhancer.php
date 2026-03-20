<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Enhancer;

use Drupal\Core\Routing\Enhancer_Interface;
use Drupal\Core\Routing\Route_Object_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Routing\Route;
/**
 * Enhances an entity form route with the appropriate controller.
 */
class Entity_Route_Enhancer implements Enhancer_Interface
{
    /**
     * {@inheritdoc}
     */
    public function enhance(array $defaults, Request $request)
    {
        $route = $defaults[Route_Object_Interface::ROUTE_OBJECT];
        if (!$this->applies($route)) {
            return $defaults;
        }
        if (empty($defaults[Route_Object_Interface::CONTROLLER_NAME])) {
            if (!empty($defaults['_entity_form'])) {
                $defaults = $this->enhance_entity_form($defaults, $request);
            } elseif (!empty($defaults['_entity_list'])) {
                $defaults = $this->enhance_entity_list($defaults, $request);
            } elseif (!empty($defaults['_entity_view'])) {
                $defaults = $this->enhance_entity_view($defaults, $request);
            }
        }
        return $defaults;
    }
    /**
     * Returns whether the enhancer runs on the current route.
     *
     * @param \Symfony\Component\Routing\Route $route
     *   The current route.
     *
     * @return bool
     *   TRUE when the route enhancer runs on the current route, FALSE otherwise.
     */
    protected function applies(Route $route): bool
    {
        return !$route->has_default(Route_Object_Interface::CONTROLLER_NAME) && ($route->has_default('_entity_form') || $route->has_default('_entity_list') || $route->has_default('_entity_view'));
    }
    /**
     * Update defaults for entity forms.
     *
     * @param array $defaults
     *   The defaults to modify.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The Request instance.
     *
     * @return array
     *   The modified defaults.
     */
    protected function enhance_entity_form(array $defaults, Request $request): array
    {
        $defaults[Route_Object_Interface::CONTROLLER_NAME] = 'controller.entity_form:getContentResult';
        return $defaults;
    }
    /**
     * Update defaults for an entity list.
     *
     * @param array $defaults
     *   The defaults to modify.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The Request instance.
     *
     * @return array
     *   The modified defaults.
     */
    protected function enhance_entity_list(array $defaults, Request $request): array
    {
        $defaults[Route_Object_Interface::CONTROLLER_NAME] = '\Drupal\Core\Entity\Controller\EntityListController::listing';
        $defaults['entity_type'] = $defaults['_entity_list'];
        unset($defaults['_entity_list']);
        return $defaults;
    }
    /**
     * Update defaults for an entity view.
     *
     * @param array $defaults
     *   The defaults to modify.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The Request instance.
     *
     * @return array
     *   The modified defaults.
     *
     * @throws \RuntimeException
     *   Thrown when an entity of a type cannot be found in a route.
     */
    protected function enhance_entity_view(array $defaults, Request $request): array
    {
        $defaults[Route_Object_Interface::CONTROLLER_NAME] = '\Drupal\Core\Entity\Controller\EntityViewController::view';
        if (str_contains((string) $defaults['_entity_view'], '.')) {
            // The _entity_view entry is of the form entity_type.view_mode.
            [$entity_type, $view_mode] = explode('.', (string) $defaults['_entity_view']);
            $defaults['view_mode'] = $view_mode;
        } else {
            // Only the entity type is nominated, the view mode will use the
            // default.
            $entity_type = $defaults['_entity_view'];
        }
        // Set by reference so that we get the upcast value.
        if (!empty($defaults[$entity_type])) {
            $defaults['_entity'] =& $defaults[$entity_type];
        } else {
            // The entity is not keyed by its entity_type. Attempt to find it
            // using a converter.
            $route = $defaults[Route_Object_Interface::ROUTE_OBJECT];
            if ($route && is_object($route)) {
                $options = $route->get_options();
                if (isset($options['parameters'])) {
                    foreach ($options['parameters'] as $name => $details) {
                        if (!empty($details['type'])) {
                            $type = $details['type'];
                            // Type is of the form entity:{entity_type}.
                            $parameter_entity_type = substr((string) $type, strlen('entity:'));
                            if ($entity_type == $parameter_entity_type) {
                                // We have the matching entity type. Set the '_entity' key
                                // to point to this named placeholder. The entity in this
                                // position is the one being rendered.
                                $defaults['_entity'] =& $defaults[$name];
                            }
                        }
                    }
                } else {
                    throw new \RuntimeException(sprintf('Failed to find entity of type %s in route named %s', $entity_type, $defaults[Route_Object_Interface::ROUTE_NAME]));
                }
            } else {
                throw new \RuntimeException(sprintf('Failed to find entity of type %s in route named %s', $entity_type, $defaults[Route_Object_Interface::ROUTE_NAME]));
            }
        }
        unset($defaults['_entity_view']);
        return $defaults;
    }
}