<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Datetime\Date_Formatter_Interface;
use Drupal\Core\Dependency_Injection\Container_Injection_Interface;
use Drupal\Core\Entity\Entity_Repository_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Entity\Revisionable_Interface;
use Drupal\Core\Entity\Revision_Log_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\String_Translation\Translation_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Defines a controller to view an entity revision.
 */
class Entity_Revision_View_Controller implements Container_Injection_Interface
{
    use String_Translation_Trait;
    /**
     * Creates a new EntityRevisionViewController.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
     *   The entity repository.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
     *   The date formatter.
     * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
     *   The string translation manager.
     */
    public function __construct(protected Entity_Type_Manager_Interface $entity_type_manager, protected Entity_Repository_Interface $entity_repository, protected Date_Formatter_Interface $date_formatter, Translation_Interface $translation)
    {
        $this->set_string_translation($translation);
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container): static
    {
        return new static($container->get('entity_type.manager'), $container->get('entity.repository'), $container->get('date.formatter'), $container->get('string_translation'));
    }
    /**
     * Provides a page to render a single entity revision.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $_entity_revision
     *   The Entity to be rendered. Note this variable is named $_entity_revision
     *   rather than $entity to prevent collisions with other named placeholders
     *   in the route.
     * @param string $view_mode
     *   (optional) The view mode that should be used to display the entity.
     *   Defaults to 'full'.
     *
     * @return array
     *   A render array.
     */
    public function __invoke(Revisionable_Interface $_entity_revision, string $view_mode = 'full'): array
    {
        $entity_type_id = $_entity_revision->get_entity_type_id();
        $page = $this->entity_type_manager->get_view_builder($entity_type_id)->view($_entity_revision, $view_mode);
        $page['#entity_type'] = $entity_type_id;
        $page['#' . $entity_type_id] = $_entity_revision;
        return $page;
    }
    /**
     * Provides a title callback for a revision of an entity.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $_entity_revision
     *   The revisionable entity, passed in directly from request attributes.
     *
     * @return \Drupal\Core\StringTranslation\TranslatableMarkup
     *   The title for the entity revision view page.
     */
    public function title(Revisionable_Interface $_entity_revision): Translatable_Markup
    {
        $revision = $this->entity_repository->get_translation_from_context($_entity_revision);
        $title_args = ['%title' => $revision->label()];
        if (!$revision instanceof Revision_Log_Interface) {
            return $this->t('Revision of %title', $title_args);
        }
        $title_args['%date'] = $this->date_formatter->format($revision->get_revision_creation_time());
        return $this->t('Revision of %title from %date', $title_args);
    }
}