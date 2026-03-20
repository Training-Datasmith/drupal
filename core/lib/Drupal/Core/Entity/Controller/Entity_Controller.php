<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Dependency_Injection\Container_Injection_Interface;
use Drupal\Core\Entity\Entity_Description_Interface;
use Drupal\Core\Entity\Entity_Interface;
use Drupal\Core\Entity\Entity_Repository_Interface;
use Drupal\Core\Entity\Entity_Type_Bundle_Info_Interface;
use Drupal\Core\Entity\Entity_Type_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderer_Interface;
use Drupal\Core\Routing\Route_Match_Interface;
use Drupal\Core\Routing\Url_Generator_Interface;
use Drupal\Core\String_Translation\String_Translation_Trait;
use Drupal\Core\String_Translation\Translation_Interface;
use Drupal\Core\Url;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Http_Foundation\Redirect_Response;
use Symfony\Component\Http_Foundation\Request;
/**
 * Provides the add-page and title callbacks for entities.
 *
 * It provides:
 * - The add-page callback.
 * - An add title callback for entity types.
 * - An add title callback for entity types with bundles.
 * - A view title callback.
 * - An edit title callback.
 * - A delete title callback.
 */
class Entity_Controller implements Container_Injection_Interface
{
    use String_Translation_Trait;
    /**
     * Constructs a new EntityController.
     */
    public function __construct(protected readonly Entity_Type_Manager_Interface $entity_type_manager, protected readonly Entity_Type_Bundle_Info_Interface $entity_type_bundle_info, protected readonly Entity_Repository_Interface $entity_repository, protected readonly Renderer_Interface $renderer, Translation_Interface $string_translation, protected readonly Url_Generator_Interface $url_generator, protected readonly Route_Match_Interface $route_match)
    {
        $this->string_translation = $string_translation;
    }
    /**
     * {@inheritdoc}
     */
    public static function create(Container_Interface $container): static
    {
        return new static($container->get('entity_type.manager'), $container->get('entity_type.bundle.info'), $container->get('entity.repository'), $container->get('renderer'), $container->get('string_translation'), $container->get('url_generator'), $container->get('current_route_match'));
    }
    /**
     * Returns a redirect response object for the specified route.
     *
     * @param string $route_name
     *   The name of the route to which to redirect.
     * @param array $route_parameters
     *   (optional) Parameters for the route.
     * @param array $options
     *   (optional) An associative array of additional options.
     * @param int $status
     *   (optional) The HTTP redirect status code for the redirect. The default is
     *   302 Found.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *   A redirect response object that may be returned by the controller.
     */
    protected function redirect($route_name, array $route_parameters = [], array $options = [], $status = 302)
    {
        $options['absolute'] = true;
        return new Redirect_Response(Url::from_route($route_name, $route_parameters, $options)->to_string(), $status);
    }
    /**
     * Displays add links for the available bundles.
     *
     * Redirects to the add form if there's only one bundle available.
     *
     * @param string $entity_type_id
     *   The entity type ID.
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request object.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
     *   If there's only one available bundle, a redirect response.
     *   Otherwise, a render array with the add links for each bundle.
     */
    public function add_page(string $entity_type_id, Request $request)
    {
        $entity_type = $this->entity_type_manager->get_definition($entity_type_id);
        $bundles = $this->entity_type_bundle_info->get_bundle_info($entity_type_id);
        $bundle_key = $entity_type->get_key('bundle');
        $bundle_entity_type_id = $entity_type->get_bundle_entity_type();
        $build = ['#theme' => 'entity_add_list', '#bundles' => []];
        if ($bundle_entity_type_id) {
            $bundle_argument = $bundle_entity_type_id;
            $bundle_entity_type = $this->entity_type_manager->get_definition($bundle_entity_type_id);
            $bundle_entity_type_label = $bundle_entity_type->get_singular_label();
            $build['#cache']['tags'] = $bundle_entity_type->get_list_cache_tags();
            // Build the message shown when there are no bundles.
            $link_text = $this->t('Add a new @entity_type.', ['@entity_type' => $bundle_entity_type_label]);
            $link_route_name = 'entity.' . $bundle_entity_type->id() . '.add_form';
            $build['#add_bundle_message'] = $this->t('There is no @entity_type yet. @add_link', ['@entity_type' => $bundle_entity_type_label, '@add_link' => Link::create_from_route($link_text, $link_route_name)->to_string()]);
            // Filter out the bundles the user doesn't have access to.
            $access_control_handler = $this->entity_type_manager->get_access_control_handler($entity_type_id);
            foreach ($bundles as $bundle_name => $bundle_info) {
                $access = $access_control_handler->create_access($bundle_name, null, [], true);
                if (!$access->is_allowed()) {
                    unset($bundles[$bundle_name]);
                }
                $this->renderer->add_cacheable_dependency($build, $access);
            }
            // Add descriptions from the bundle entities.
            $bundles = $this->load_bundle_descriptions($bundles, $bundle_entity_type);
        } else {
            $bundle_argument = $bundle_key;
        }
        $form_route_name = 'entity.' . $entity_type_id . '.add_form';
        // Redirect if there's only one bundle available.
        if (count($bundles) == 1) {
            $bundle_names = array_keys($bundles);
            $bundle_name = reset($bundle_names);
            $parameters = $this->route_match->get_raw_parameters()->all();
            $parameters[$bundle_argument] = $bundle_name;
            $query = $request->query->all();
            return $this->redirect($form_route_name, $parameters, ['query' => $query]);
        }
        // Prepare the #bundles array for the template.
        foreach ($bundles as $bundle_name => $bundle_info) {
            $build['#bundles'][$bundle_name] = ['label' => $bundle_info['label'], 'description' => $bundle_info['description'] ?? '', 'add_link' => Link::create_from_route($bundle_info['label'], $form_route_name, [$bundle_argument => $bundle_name])];
        }
        return $build;
    }
    /**
     * Provides a generic add title callback for an entity type.
     *
     * @param string $entity_type_id
     *   The entity type ID.
     *
     * @return string
     *   The title for the entity add page.
     */
    public function add_title($entity_type_id)
    {
        $entity_type = $this->entity_type_manager->get_definition($entity_type_id);
        return $this->t('Add @entity-type', ['@entity-type' => $entity_type->get_singular_label()]);
    }
    /**
     * Provides a generic add title callback for entities with bundles.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     * @param string $entity_type_id
     *   The entity type ID.
     * @param string $bundle_parameter
     *   The name of the route parameter that holds the bundle.
     *
     * @return string
     *   The title for the entity add page, if the bundle was found.
     */
    public function add_bundle_title(Route_Match_Interface $route_match, $entity_type_id, $bundle_parameter)
    {
        $bundles = $this->entity_type_bundle_info->get_bundle_info($entity_type_id);
        // If the entity has bundle entities, the parameter might have been upcasted
        // so fetch the raw parameter.
        $bundle = $route_match->get_raw_parameter($bundle_parameter);
        if (count($bundles) > 1 && isset($bundles[$bundle])) {
            return $this->t('Add @bundle', ['@bundle' => $bundles[$bundle]['label']]);
        }
        return $this->add_title($entity_type_id);
    }
    /**
     * Provides a generic title callback for a single entity.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     * @param \Drupal\Core\Entity\EntityInterface $_entity
     *   (optional) An entity, passed in directly from the request attributes.
     *
     * @return string|null
     *   The title for the entity view page, if an entity was found.
     */
    public function title(Route_Match_Interface $route_match, ?Entity_Interface $_entity = null)
    {
        if ($entity = $this->do_get_entity($route_match, $_entity)) {
            return $entity->label();
        }
    }
    /**
     * Provides a generic edit title callback.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     * @param \Drupal\Core\Entity\EntityInterface $_entity
     *   (optional) An entity, passed in directly from the request attributes.
     *
     * @return string|null
     *   The title for the entity edit page, if an entity was found.
     */
    public function edit_title(Route_Match_Interface $route_match, ?Entity_Interface $_entity = null)
    {
        $entity = $this->do_get_entity($route_match, $_entity);
        if ($entity === null) {
            return null;
        }
        $label = $entity->label();
        if ($label === null) {
            return $this->t('Edit');
        }
        return $this->t('Edit %label', ['%label' => $label]);
    }
    /**
     * Provides a generic delete title callback.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     * @param \Drupal\Core\Entity\EntityInterface $_entity
     *   (optional) An entity, passed in directly from the request attributes, and
     *   set in \Drupal\Core\Entity\Enhancer\EntityRouteEnhancer.
     *
     * @return string
     *   The title for the entity delete page.
     */
    public function delete_title(Route_Match_Interface $route_match, ?Entity_Interface $_entity = null)
    {
        $entity = $this->do_get_entity($route_match, $_entity);
        if ($entity === null) {
            return '';
        }
        $label = $entity->label();
        if ($label === null) {
            return $this->t('Delete');
        }
        return $this->t('Delete %label', ['%label' => $label]);
    }
    /**
     * Determines the entity.
     *
     * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
     *   The route match.
     * @param \Drupal\Core\Entity\EntityInterface $_entity
     *   (optional) The entity, set in
     *   \Drupal\Core\Entity\Enhancer\EntityRouteEnhancer.
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     *   The entity, if it is passed in directly or if the first parameter of the
     *   active route is an entity; otherwise, NULL.
     */
    protected function do_get_entity(Route_Match_Interface $route_match, ?Entity_Interface $_entity = null)
    {
        if ($_entity) {
            $entity = $_entity;
        } else {
            // Let's look up in the route object for the name of upcasted values.
            foreach ($route_match->get_parameters() as $parameter) {
                if ($parameter instanceof Entity_Interface) {
                    $entity = $parameter;
                    break;
                }
            }
        }
        if (isset($entity)) {
            return $this->entity_repository->get_translation_from_context($entity);
        }
    }
    /**
     * Expands the bundle information with descriptions, if known.
     *
     * Also sorts the bundles before adding the bundle descriptions. Sorting is
     * being done here to avoid having to load bundle entities multiple times.
     *
     * @param array $bundles
     *   An array of bundle information.
     * @param \Drupal\Core\Entity\EntityTypeInterface $bundle_entity_type
     *   The bundle entity type definition.
     *
     * @return array
     *   An array of sorted bundle information including bundle descriptions.
     */
    protected function load_bundle_descriptions(array $bundles, Entity_Type_Interface $bundle_entity_type): array
    {
        if (!$bundle_entity_type->entity_class_implements(Entity_Description_Interface::class)) {
            return $bundles;
        }
        $bundle_names = array_keys($bundles);
        $storage = $this->entity_type_manager->get_storage($bundle_entity_type->id());
        /** @var \Drupal\Core\Entity\EntityDescriptionInterface[] $bundle_entities */
        $bundle_entities = $storage->load_multiple($bundle_names);
        uasort($bundle_entities, [$bundle_entity_type->get_class(), 'sort']);
        $bundles = array_replace($bundle_entities, $bundles);
        foreach ($bundles as $bundle_name => &$bundle_info) {
            if (isset($bundle_entities[$bundle_name])) {
                $bundle_info['description'] = $bundle_entities[$bundle_name]->get_description();
            }
        }
        return $bundles;
    }
}