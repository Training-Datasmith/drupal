<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

/**
 * Class BundleEntityFormBase is a base form for bundle config entities.
 */
class Bundle_Entity_Form_Base extends Entity_Form
{
    /**
     * Protects the bundle entity's ID property's form element against changes.
     *
     * This method is assumed to be called on a completely built entity form,
     * including a form element for the bundle config entity's ID property.
     *
     * @param array $form
     *   The completely built entity bundle form array.
     *
     * @return array
     *   The updated entity bundle form array.
     */
    protected function protect_bundle_id_element(array $form): array
    {
        $entity = $this->get_entity();
        $id_key = $entity->get_entity_type()->get_key('id');
        assert(isset($form[$id_key]));
        $element =& $form[$id_key];
        // Make sure the element is not accidentally re-enabled if it has already
        // been disabled.
        if (empty($element['#disabled'])) {
            $element['#disabled'] = !$entity->is_new();
        }
        return $form;
    }
}