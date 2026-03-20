<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Annotation;

use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Defines a content entity type annotation object.
 *
 * Content Entity type plugins use an object-based annotation method, rather
 * than an array-type annotation method (as commonly used on other annotation
 * types). The annotation properties of content entity types are found on
 * \Drupal\Core\Entity\ContentEntityType and are accessed using get/set methods
 * defined in \Drupal\Core\Entity\ContentEntityTypeInterface.
 *
 * @ingroup entity_api
 *
 * @Annotation
 */
class Content_Entity_Type extends Entity_Type
{
    /**
     * {@inheritdoc}
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    public $entity_type_class = \Drupal\Core\Entity\Content_Entity_Type::class;
    /**
     * {@inheritdoc}
     */
    public $group = 'content';
    /**
     * {@inheritdoc}
     */
    public function get()
    {
        $this->definition['group_label'] = new Translatable_Markup('Content', [], ['context' => 'Entity type group']);
        return parent::get();
    }
}