<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cacheable_Metadata;
use Drupal\Core\Controller\Controller_Base;
use Drupal\Core\Datetime\Date_Formatter_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Entity\Revisionable_Interface;
use Drupal\Core\Entity\Revisionable_Storage_Interface;
use Drupal\Core\Entity\Revision_Log_Interface;
use Drupal\Core\Language\Language_Interface;
use Drupal\Core\Language\Language_Manager_Interface;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderer_Interface;
use Drupal\Core\Routing\Route_Match_Interface;
/**
 * Provides a controller showing revision history for an entity.
 *
 * This controller is agnostic to any entity type by using
 * \Drupal\Core\Entity\RevisionLogInterface.
 *
 * For full functionality, entity types should define the following link
 * templates in their attributes:
 * - 'revision-revert-form': Path to the form for reverting a revision.
 *   Required to show revert links in the revision history table.
 * - 'revision-delete-form': Path to the form for deleting a revision.
 *   Required to show delete links in the revision history table.
 * - 'revision': Path to view a specific revision. Used to make revision
 *   dates/labels clickable links to the revision view.
 *
 * @see \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider
 * @see \Drupal\Core\Entity\RevisionableInterface
 */
class Version_History_Controller extends Controller_Base
{
    public const REVISIONS_PER_PAGE = 50;
    /**
     * Constructs a new VersionHistoryController.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
     *   The date formatter service.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     */
    public function __construct(Entity_Type_Manager_Interface $entity_type_manager, Language_Manager_Interface $language_manager, protected Date_Formatter_Interface $date_formatter, protected Renderer_Interface $renderer)
    {
        $this->entity_type_manager = $entity_type_manager;
        $this->language_manager = $language_manager;
    }
    /**
     * Generates an overview table of revisions for an entity.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
     *   The route match.
     *
     * @return array
     *   A render array.
     */
    public function __invoke(Route_Match_Interface $route_match): array
    {
        $entity_type_id = $route_match->get_route_object()->get_option('entity_type_id');
        $entity = $route_match->get_parameter($entity_type_id);
        return $this->revision_overview($entity);
    }
    /**
     * Builds a link to revert an entity revision.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $revision
     *   The entity to build a revert revision link for.
     *
     * @return array|null
     *   A link to revert an entity revision, or NULL if the entity type does not
     *   define a 'revision-revert-form' link template or the user does not have
     *   access to the revert form.
     */
    protected function build_revert_revision_link(Revisionable_Interface $revision): ?array
    {
        if (!$revision->has_link_template('revision-revert-form')) {
            return null;
        }
        $url = $revision->to_url('revision-revert-form');
        // @todo Merge in cacheability after
        // https://www.drupal.org/project/drupal/issues/2473873.
        if (!$url->access()) {
            return null;
        }
        return ['title' => $this->t('Revert'), 'url' => $url];
    }
    /**
     * Builds a link to delete an entity revision.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $revision
     *   The entity to build a delete revision link for.
     *
     * @return array|null
     *   A link render array, or NULL if the entity type does not define a
     *   'revision-delete-form' link template or the user does not have access
     *   to the delete form.
     */
    protected function build_delete_revision_link(Revisionable_Interface $revision): ?array
    {
        if (!$revision->has_link_template('revision-delete-form')) {
            return null;
        }
        $url = $revision->to_url('revision-delete-form');
        // @todo Merge in cacheability after
        // https://www.drupal.org/project/drupal/issues/2473873.
        if (!$url->access()) {
            return null;
        }
        return ['title' => $this->t('Delete'), 'url' => $url];
    }
    /**
     * Get a description of the revision.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $revision
     *   The entity revision.
     *
     * @return array
     *   A render array describing the revision.
     */
    protected function get_revision_description(Revisionable_Interface $revision): array
    {
        $context = [];
        if ($revision instanceof Revision_Log_Interface) {
            // Use revision link to link to revisions that are not active.
            ['type' => $date_format_type, 'format' => $date_format_format] = $this->get_revision_description_date_format($revision);
            $link_text = $this->date_formatter->format($revision->get_revision_creation_time(), $date_format_type, $date_format_format);
            $context['username'] = ['#theme' => 'username', '#account' => $revision->get_revision_user()];
        } else {
            $link_text = $revision->access('view label') ? $revision->label() : $this->t('- Restricted access -');
        }
        $url = $revision->has_link_template('revision') ? $revision->to_url('revision') : null;
        $context['revision'] = $url && $url->access() ? Link::from_text_and_url($link_text, $url)->to_string() : (string) $link_text;
        $context['message'] = $revision instanceof Revision_Log_Interface ? ['#markup' => $revision->get_revision_log_message(), '#allowed_tags' => Xss::get_html_tag_list()] : '';
        return ['data' => ['#type' => 'inline_template', '#template' => isset($context['username']) ? '{% trans %} {{ revision }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}' : '{% trans %} {{ revision }} {% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}', '#context' => $context]];
    }
    /**
     * Date format to use for revision description dates.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $revision
     *   The revision in context.
     *
     * @return array
     *   An array with keys 'type' and optionally 'format' suitable for passing
     *   to date formatter service.
     */
    protected function get_revision_description_date_format(Revisionable_Interface $revision): array
    {
        return ['type' => 'short', 'format' => ''];
    }
    /**
     * Generates revisions of an entity relevant to the current language.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $entity
     *   The entity.
     *
     * @return \Generator|\Drupal\Core\Entity\RevisionableInterface
     *   Generates revisions.
     */
    protected function load_revisions(Revisionable_Interface $entity)
    {
        $entity_type = $entity->get_entity_type();
        $translatable = $entity_type->is_translatable();
        $entity_storage = $this->entity_type_manager->get_storage($entity->get_entity_type_id());
        assert($entity_storage instanceof Revisionable_Storage_Interface);
        $query = $entity_storage->get_query()->access_check(false)->all_revisions()->condition($entity_type->get_key('id'), $entity->id())->sort($entity_type->get_key('revision'), 'DESC')->pager(self::REVISIONS_PER_PAGE);
        // Only show revisions that are affected by the language that is being
        // displayed.
        if ($translatable) {
            $query->condition($entity_type->get_key('langcode'), $entity->language()->get_id())->condition($entity_type->get_key('revision_translation_affected'), '1');
        }
        $result = $query->execute();
        $current_langcode = $this->language_manager->get_current_language(Language_Interface::TYPE_CONTENT)->get_id();
        foreach ($entity_storage->load_multiple_revisions(array_keys($result)) as $revision) {
            yield $translatable ? $revision->get_translation($current_langcode) : $revision;
        }
    }
    /**
     * Generates an overview table of revisions of an entity.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $entity
     *   A revisionable entity.
     *
     * @return array
     *   A render array.
     */
    protected function revision_overview(Revisionable_Interface $entity): array
    {
        $build['entity_revisions_table'] = ['#theme' => 'table', '#header' => ['revision' => ['data' => $this->t('Revision')], 'operations' => ['data' => $this->t('Operations')]]];
        foreach ($this->load_revisions($entity) as $revision) {
            $build['entity_revisions_table']['#rows'][$revision->get_revision_id()] = $this->build_row($revision);
        }
        $build['pager'] = ['#type' => 'pager'];
        (new Cacheable_Metadata())->add_cacheable_dependency($entity)->add_cache_contexts(['languages:language_content'])->apply_to($build);
        return $build;
    }
    /**
     * Builds a table row for a revision.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $revision
     *   An entity revision.
     *
     * @return array
     *   A table row.
     */
    protected function build_row(Revisionable_Interface $revision): array
    {
        $row = [];
        $row_attributes = [];
        $row['revision']['data'] = $this->get_revision_description($revision);
        $row['operations']['data'] = [];
        // Revision status.
        if ($revision->is_default_revision()) {
            $row_attributes['class'][] = 'revision-current';
            $row['operations']['data']['status']['#markup'] = $this->t('<em>Current revision</em>');
        }
        // Operation links.
        $links = $this->get_operation_links($revision);
        if (count($links) > 0) {
            $row['operations']['data']['operations'] = ['#type' => 'operations', '#links' => $links];
        }
        return ['data' => $row] + $row_attributes;
    }
    /**
     * Get operations for an entity revision.
     *
     * @param \Drupal\Core\Entity\RevisionableInterface $revision
     *   The entity to build revision links for.
     *
     * @return array
     *   An array of operation links.
     */
    protected function get_operation_links(Revisionable_Interface $revision): array
    {
        // Removes links which are inaccessible or not rendered.
        return array_filter([$this->build_revert_revision_link($revision), $this->build_delete_revision_link($revision)]);
    }
}