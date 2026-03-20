<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

/**
 * Provides a base entity class with extended revision and publishing support.
 *
 * @ingroup entity_api
 */
abstract class Editorial_Content_Entity_Base extends Content_Entity_Base implements Entity_Changed_Interface, Entity_Published_Interface, Revision_Log_Interface
{
    use Entity_Changed_Trait;
    use Entity_Published_Trait;
    use Revision_Log_Entity_Trait;
    /**
     * {@inheritdoc}
     */
    public static function base_field_definitions(Entity_Type_Interface $entity_type)
    {
        $fields = parent::base_field_definitions($entity_type);
        // Add the revision metadata fields.
        $fields += static::revision_log_base_field_definitions($entity_type);
        // Add the published field.
        $fields += static::published_base_field_definitions($entity_type);
        return $fields;
    }
}