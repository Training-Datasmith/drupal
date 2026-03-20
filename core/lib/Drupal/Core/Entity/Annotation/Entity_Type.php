<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\String_Translation\String_Translation_Trait;
/**
 * Defines an Entity type annotation object.
 *
 * Entity type plugins use an object-based annotation method, rather than an
 * array-type annotation method (as commonly used on other annotation types).
 * The annotation properties of entity types are found on
 * \Drupal\Core\Entity\EntityType and are accessed using get/set methods defined
 * in \Drupal\Core\Entity\EntityTypeInterface.
 *
 * @ingroup entity_api
 *
 * @Annotation
 */
class Entity_Type extends Plugin
{
    use String_Translation_Trait;
    /**
     * The class used to represent the entity type.
     *
     * It must implement \Drupal\Core\Entity\EntityTypeInterface.
     *
     * @var string
     */
    public $entity_type_class = \Drupal\Core\Entity\Entity_Type::class;
    /**
     * The group machine name.
     *
     * @var string
     */
    public $group = 'default';
    /**
     * The group label.
     *
     * @var \Drupal\Core\Annotation\Translation
     *
     * @ingroup plugin_translatable
     */
    public $group_label = '';
    /**
     * {@inheritdoc}
     */
    public function get()
    {
        $values = $this->definition;
        // Use the specified entity type class, and remove it before instantiating.
        $class = $values['entity_type_class'];
        unset($values['entity_type_class']);
        return new $class($values);
    }
}