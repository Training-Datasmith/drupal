<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Extension\Module_Uninstall_Validator_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translation_Interface;
use Drupal\Core\Url;
/**
 * Validates module uninstall readiness based on existing content entities.
 */
class Content_Uninstall_Validator implements Module_Uninstall_Validator_Interface
{
    use String_Translation_Trait;
    /**
     * Constructs a new ContentUninstallValidator.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager service.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
     *   The string translation service.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Type_Manager_Interface $entity_type_manager, Translation_Interface $string_translation)
    {
        $this->string_translation = $string_translation;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function validate($module): array
    {
        $entity_types = $this->entity_type_manager->get_definitions();
        $reasons = [];
        foreach ($entity_types as $entity_type) {
            if ($module == $entity_type->get_provider() && $entity_type instanceof Content_Entity_Type_Interface && $this->entity_type_manager->get_storage($entity_type->id())->has_data()) {
                $reasons[] = $this->t('There is content for the entity type: @entity_type. <a href=":url">Remove @entity_type_plural</a>.', ['@entity_type' => $entity_type->get_label(), '@entity_type_plural' => $entity_type->get_plural_label(), ':url' => Url::from_route('system.prepare_modules_entity_uninstall', ['entity_type_id' => $entity_type->id()])->to_string()]);
            }
        }
        return $reasons;
    }
}