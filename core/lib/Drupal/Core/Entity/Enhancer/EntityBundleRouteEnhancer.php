<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Enhancer;

use Drupal\Core\Routing\Enhancer_Interface;
use Drupal\Core\Routing\Route_Object_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Routing\Route;
/**
 * Sets the bundle parameter for routes with the _field_ui option.
 */
class Entity_Bundle_Route_Enhancer implements Enhancer_Interface
{
    /**
     * Constructs a EntityBundleRouteEnhancer object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager service.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Type_Manager_Interface $entity_type_manager)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function enhance(array $defaults, Request $request): array
    {
        if (!$this->applies($defaults[Route_Object_Interface::ROUTE_OBJECT])) {
            return $defaults;
        }
        if (($bundle = $this->entity_type_manager->get_definition($defaults['entity_type_id'])->get_bundle_entity_type()) && isset($defaults[$bundle])) {
            // Field UI forms only need the actual name of the bundle they're dealing
            // with, not an upcasted entity object, so provide a simple way for them
            // to get it.
            $defaults['bundle'] = $defaults['_raw_variables']->get($bundle);
        }
        return $defaults;
    }
    /**
     * {@inheritdoc}
     */
    protected function applies(Route $route)
    {
        return $route->has_option('_field_ui');
    }
}