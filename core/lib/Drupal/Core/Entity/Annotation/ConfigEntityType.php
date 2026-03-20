<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Annotation;

use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Defines a config entity type annotation object.
 *
 * The annotation properties of entity types are found on
 * \Drupal\Core\Config\Entity\ConfigEntityType and are accessed using
 * get/set methods defined in \Drupal\Core\Entity\EntityTypeInterface.
 *
 * @ingroup entity_api
 *
 * @Annotation
 */
class Config_Entity_Type extends Entity_Type
{
    /**
     * {@inheritdoc}
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    public $entity_type_class = \Drupal\Core\Config\Entity\Config_Entity_Type::class;
    /**
     * {@inheritdoc}
     */
    public $group = 'configuration';
    /**
     * {@inheritdoc}
     */
    public function get()
    {
        $this->definition['group_label'] = new Translatable_Markup('Configuration', [], ['context' => 'Entity type group']);
        return parent::get();
    }
}