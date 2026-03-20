<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Entity;

use Drupal\Component\Utility\Nested_Array;
use Drupal\Core\Entity\Attribute\Config_Entity_Type;
use Drupal\Core\Entity\Display\Entity_View_Display_Interface;
use Drupal\Core\Entity\Entity\Access\Entity_View_Display_Access_Control_Handler;
use Drupal\Core\Entity\Entity_Display_Base;
use Drupal\Core\Entity\Entity_Display_Plugin_Collection;
use Drupal\Core\Entity\Entity_Storage_Interface;
use Drupal\Core\Entity\Fieldable_Entity_Interface;
use Drupal\Core\Render\Element;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Typed_Data\Translatable_Interface as TranslatableDataInterface;
/**
 * Configuration entity.
 *
 * Contains display options for all components of a rendered entity in a given
 * view mode.
 */
#[Config_Entity_Type(id: 'entity_view_display', label: new Translatable_Markup('Entity view display'), entity_keys: ['id' => 'id', 'status' => 'status'], handlers: ['access' => Entity_View_Display_Access_Control_Handler::class], constraints: ['ImmutableProperties' => ['properties' => ['id', 'targetEntityType', 'bundle', 'mode']]], config_export: ['id', 'targetEntityType', 'bundle', 'mode', 'content', 'hidden'])]
class Entity_View_Display extends Entity_Display_Base implements Entity_View_Display_Interface
{
    /**
     * {@inheritdoc}
     */
    protected $display_context = 'view';
    /**
     * Returns the display objects used to render a set of entities.
     *
     * Depending on the configuration of the view mode for each bundle, this can
     * be either the display object associated with the view mode, or the
     * 'default' display.
     *
     * This method should only be used internally when rendering an entity. When
     * assigning suggested display options for a component in a given view mode,
     * EntityDisplayRepositoryInterface::getViewDisplay() should be used instead,
     * in order to avoid inadvertently modifying the output of other view modes
     * that might happen to use the 'default' display too. Those options will then
     * be effectively applied only if the view mode is configured to use them.
     *
     * hook_entity_view_display_alter() is invoked on each display, allowing 3rd
     * party code to alter the display options held in the display before they are
     * used to generate render arrays.
     *
     * @param \Drupal\Core\Entity\FieldableEntityInterface[] $entities
     *   The entities being rendered. They should all be of the same entity type.
     * @param string $view_mode
     *   The view mode being rendered.
     *
     * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface[]
     *   The display objects to use to render the entities, keyed by entity
     *   bundle.
     *
     * @see \Drupal\Core\Entity\EntityDisplayRepositoryInterface::getViewDisplay()
     * @see hook_entity_view_display_alter()
     */
    public static function collect_render_displays($entities, $view_mode): array
    {
        if (empty($entities)) {
            return [];
        }
        // Collect entity type and bundles.
        $entity_type = current($entities)->get_entity_type_id();
        $bundles = [];
        foreach ($entities as $entity) {
            $bundles[$entity->bundle()] = true;
        }
        $bundles = array_keys($bundles);
        // For each bundle, check the existence and status of:
        // - the display for the view mode,
        // - the 'default' display.
        $candidate_ids = [];
        foreach ($bundles as $bundle) {
            if ($view_mode != 'default') {
                $candidate_ids[$bundle][] = $entity_type . '.' . $bundle . '.' . $view_mode;
            }
            $candidate_ids[$bundle][] = $entity_type . '.' . $bundle . '.default';
        }
        $results = \Drupal::entity_query('entity_view_display')->condition('id', Nested_Array::merge_deep_array($candidate_ids))->condition('status', true)->execute();
        // For each bundle, select the first valid candidate display, if any.
        $load_ids = [];
        foreach ($bundles as $bundle) {
            foreach ($candidate_ids[$bundle] as $candidate_id) {
                if (isset($results[$candidate_id])) {
                    $load_ids[$bundle] = $candidate_id;
                    break;
                }
            }
        }
        // Load the selected displays.
        $storage = \Drupal::entity_type_manager()->get_storage('entity_view_display');
        $displays = $storage->load_multiple($load_ids);
        $displays_by_bundle = [];
        foreach ($bundles as $bundle) {
            // Use the selected display if any, or create a fresh runtime object.
            if (isset($load_ids[$bundle])) {
                $display = $displays[$load_ids[$bundle]];
            } else {
                $display = $storage->create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => $view_mode, 'status' => true]);
            }
            // Let the display know which view mode was originally requested.
            $display->original_mode = $view_mode;
            // Let modules alter the display.
            $display_context = ['entity_type' => $entity_type, 'bundle' => $bundle, 'view_mode' => $view_mode];
            \Drupal::module_handler()->alter('entity_view_display', $display, $display_context);
            $displays_by_bundle[$bundle] = $display;
        }
        return $displays_by_bundle;
    }
    /**
     * Returns the display object used to render an entity.
     *
     * See the collectRenderDisplays() method for details.
     *
     * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
     *   The entity being rendered.
     * @param string $view_mode
     *   The view mode.
     *
     * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
     *   The display object that should be used to render the entity.
     *
     * @see \Drupal\Core\Entity\Entity\EntityViewDisplay::collectRenderDisplays()
     */
    public static function collect_render_display(Fieldable_Entity_Interface $entity, $view_mode)
    {
        $displays = static::collect_render_displays([$entity], $view_mode);
        return $displays[$entity->bundle()];
    }
    /**
     * {@inheritdoc}
     */
    public function __construct(array $values, $entity_type)
    {
        $this->plugin_manager = \Drupal::service('plugin.manager.field.formatter');
        parent::__construct($values, $entity_type);
    }
    /**
     * {@inheritdoc}
     */
    public function post_save(Entity_Storage_Interface $storage, $update = true): void
    {
        // Reset the render cache for the target entity type.
        parent::post_save($storage, $update);
        if (\Drupal::entity_type_manager()->has_handler($this->target_entity_type, 'view_builder')) {
            \Drupal::entity_type_manager()->get_view_builder($this->target_entity_type)->reset_cache();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_renderer($field_name)
    {
        if (isset($this->plugins[$field_name])) {
            return $this->plugins[$field_name];
        }
        // Instantiate the formatter object from the stored display properties.
        if (($configuration = $this->get_component($field_name)) && isset($configuration['type']) && $definition = $this->get_field_definition($field_name)) {
            $formatter = $this->plugin_manager->get_instance([
                'field_definition' => $definition,
                'view_mode' => $this->original_mode,
                // No need to prepare, defaults have been merged in setComponent().
                'prepare' => false,
                'configuration' => $configuration,
            ]);
        } else {
            $formatter = null;
        }
        // Persist the formatter object.
        $this->plugins[$field_name] = $formatter;
        return $formatter;
    }
    /**
     * {@inheritdoc}
     */
    public function build(Fieldable_Entity_Interface $entity)
    {
        $build = $this->build_multiple([$entity]);
        return $build[0];
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function build_multiple(array $entities): array
    {
        $build_list = [];
        foreach ($entities as $key => $entity) {
            $build_list[$key] = [];
        }
        // Run field formatters.
        foreach ($this->get_components() as $name => $options) {
            if ($formatter = $this->get_renderer($name)) {
                // Group items across all entities and pass them to the formatter's
                // prepareView() method.
                $grouped_items = [];
                foreach ($entities as $id => $entity) {
                    $items = $entity->get($name);
                    $items->filter_empty_items();
                    $grouped_items[$id] = $items;
                }
                $formatter->prepare_view($grouped_items);
                // Then let the formatter build the output for each entity.
                foreach ($entities as $id => $entity) {
                    $items = $grouped_items[$id];
                    /** @var \Drupal\Core\Access\AccessResultInterface $field_access */
                    $field_access = $items->access('view', null, true);
                    // The language of the field values to display is already determined
                    // in the incoming $entity. The formatter should build its output of
                    // those values using:
                    // - the entity language if the entity is translatable,
                    // - the current "content language" otherwise.
                    if ($entity instanceof Translatable_Data_Interface && $entity->is_translatable()) {
                        $view_langcode = $entity->language()->get_id();
                    } else {
                        $view_langcode = null;
                    }
                    $build_list[$id][$name] = $field_access->is_allowed() ? $formatter->view($items, $view_langcode) : [];
                    // Apply the field access cacheability metadata to the render array.
                    $this->renderer->add_cacheable_dependency($build_list[$id][$name], $field_access);
                }
            }
        }
        foreach ($entities as $id => $entity) {
            // Assign the configured weights.
            foreach ($this->get_components() as $name => $options) {
                if (isset($build_list[$id][$name]) && !Element::is_empty($build_list[$id][$name])) {
                    $build_list[$id][$name]['#weight'] = $options['weight'];
                }
            }
            // Let other modules alter the renderable array.
            $context = ['entity' => $entity, 'view_mode' => $this->original_mode, 'display' => $this];
            \Drupal::module_handler()->alter('entity_display_build', $build_list[$id], $context);
        }
        return $build_list;
    }
    /**
     * {@inheritdoc}
     */
    public function get_plugin_collections(): array
    {
        $configurations = [];
        foreach ($this->get_components() as $field_name => $configuration) {
            if (!empty($configuration['type']) && $field_definition = $this->get_field_definition($field_name)) {
                $configurations[$configuration['type']] = $configuration + ['field_definition' => $field_definition, 'view_mode' => $this->original_mode];
            }
        }
        return ['formatters' => new Entity_Display_Plugin_Collection($this->plugin_manager, $configurations)];
    }
}