<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Dependency_Injection\Container_Injection_Interface;
use Drupal\Core\Entity\Entity_Interface;
use Drupal\Core\Entity\Fieldable_Entity_Interface;
use Drupal\Core\Security\Trusted_Callback_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Defines a generic controller to render a single entity.
 */
class Entity_View_Controller implements Container_Injection_Interface, Trusted_Callback_Interface
{
    /**
     * Creates an EntityViewController object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer service.
     */
    public function __construct(protected \Drupal\Core\Entity\Entity_Type_Manager_Interface $entity_type_manager, protected \Drupal\Core\Render\Renderer_Interface $renderer)
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container): static
    {
        return new static($container->get('entity_type.manager'), $container->get('renderer'));
    }
    /**
     * Pre-render callback to build the page title.
     *
     * There are two possibilities, depending on the value of the additional
     * entity type property 'enable_page_title_template'.
     * - FALSE (default): use the output of the related field formatter if it
     *   exists. This approach only works correctly for the node entity type and
     *   with the 'string' formatter. In other cases it likely produces invalid
     *   markup and possibly incorrect display. This option has been retained for
     *   backward-compatibility to support sites that expect attributes set on
     *   the field to propagate to the page title.
     * - TRUE: use the output from the entity_page_title template. This approach
     *   works correctly in all cases, without relying on a particular field
     *   formatter or special templates and is the preferred option for the
     *   future.
     *
     * @param array $page
     *   A page render array.
     *
     * @return array
     *   The changed page render array.
     */
    public function build_title(array $page): array
    {
        $entity_type = $page['#entity_type'];
        $entity = $page['#' . $entity_type];
        // If the entity has a label field, build the page title based on it.
        if ($entity instanceof Fieldable_Entity_Interface) {
            $label_field = $entity->get_entity_type()->get_key('label');
            $template_enabled = $entity->get_entity_type()->get('enable_page_title_template');
            if ($label_field && $template_enabled) {
                // Set page title to the output from the entity_page_title template.
                $page_title = ['#theme' => 'entity_page_title', '#title' => $entity->label(), '#entity' => $entity, '#view_mode' => $page['#view_mode']];
                $page['#title'] = $this->renderer->render($page_title);
                // Prevent output of the label field in the main content.
                $page[$label_field]['#access'] = false;
                return $page;
            }
            // Set page title to the rendered title field formatter instead of
            // the default plain text title.
            //
            // @todo https://www.drupal.org/project/drupal/issues/3015623
            //   Eventually delete this code and always use the first approach.
            if (isset($page[$label_field])) {
                // Allow templates and theme functions to generate different markup
                // for the page title, which must be inline markup as it will be placed
                // inside <h1>.  See field--node--title.html.twig.
                $page[$label_field]['#is_page_title'] = true;
                $page['#title'] = $this->renderer->render($page[$label_field]);
            }
        }
        return $page;
    }
    /**
     * Provides a page to render a single entity.
     *
     * @param \Drupal\Core\Entity\EntityInterface $_entity
     *   The Entity to be rendered. Note this variable is named $_entity rather
     *   than $entity to prevent collisions with other named placeholders in the
     *   route.
     * @param string $view_mode
     *   (optional) The view mode that should be used to display the entity.
     *   Defaults to 'full'.
     *
     * @return array
     *   A render array as expected by
     *   \Drupal\Core\Render\RendererInterface::render().
     */
    public function view(Entity_Interface $_entity, $view_mode = 'full')
    {
        $page = $this->entity_type_manager->get_view_builder($_entity->get_entity_type_id())->view($_entity, $view_mode);
        $page['#pre_render'][] = $this->build_title(...);
        $page['#entity_type'] = $_entity->get_entity_type_id();
        $page['#' . $page['#entity_type']] = $_entity;
        // Add canonical and shortlink links if the entity has a canonical
        // link template and is not new.
        if ($_entity->has_link_template('canonical') && !$_entity->is_new()) {
            $url = $_entity->to_url('canonical')->set_absolute(true);
            $page['#attached']['html_head_link'][] = [['rel' => 'canonical', 'href' => $url->to_string()]];
            // Set the non-aliased canonical path as a default shortlink.
            $page['#attached']['html_head_link'][] = [['rel' => 'shortlink', 'href' => $url->set_option('alias', true)->to_string()]];
            // Since this generates absolute URLs, it can only be cached "per site".
            $page['#cache']['contexts'][] = 'url.site';
        }
        return $page;
    }
    /**
     * {@inheritdoc}
     */
    public static function trusted_callbacks(): array
    {
        return ['buildTitle'];
    }
}