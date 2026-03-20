<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Controller\Controller_Base;
/**
 * Defines a generic controller to list entities.
 */
class Entity_List_Controller extends Controller_Base
{
    /**
     * Provides the listing page for any entity type.
     *
     * @param string $entity_type
     *   The entity type to render.
     *
     * @return array
     *   A render array as expected by
     *   \Drupal\Core\Render\RendererInterface::render().
     */
    public function listing($entity_type)
    {
        return $this->entity_type_manager()->get_list_builder($entity_type)->render();
    }
}