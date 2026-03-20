<?php

declare (strict_types=1);
namespace Drupal\Core\Entity\Display;

use Drupal\Core\Config\Entity\Config_Entity_Interface;
use Drupal\Core\Entity\Entity_With_Plugin_Collection_Interface;
/**
 * Provides a common interface for entity displays.
 */
interface Entity_Display_Interface extends Config_Entity_Interface, Entity_With_Plugin_Collection_Interface
{
    /**
     * Creates a duplicate of the entity display object on a different view mode.
     *
     * The new object necessarily has the same $targetEntityType and $bundle
     * properties than the original one.
     *
     * @param string $view_mode
     *   The view mode for the new object.
     *
     * @return static
     *   A duplicate of this object with the given view mode.
     */
    public function create_copy($view_mode);
    /**
     * Gets the display options for all components.
     *
     * @return array
     *   The array of display options, keyed by component name.
     */
    public function get_components();
    /**
     * Gets the display options set for a component.
     *
     * @param string $name
     *   The name of the component.
     *
     * @return array|null
     *   The display options for the component, or NULL if the component is not
     *   displayed.
     */
    public function get_component($name);
    /**
     * Sets the display options for a component.
     *
     * @param string $name
     *   The name of the component.
     * @param array $options
     *   The display options.
     *
     * @return $this
     */
    public function set_component($name, array $options = []);
    /**
     * Sets a component to be hidden.
     *
     * @param string $name
     *   The name of the component.
     *
     * @return $this
     */
    public function remove_component($name);
    /**
     * Gets the highest weight of the components in the display.
     *
     * @return int|null
     *   The highest weight of the components in the display, or NULL if the
     *   display is empty.
     */
    public function get_highest_weight();
    /**
     * Gets the renderer plugin for a field (e.g. widget, formatter).
     *
     * @param string $field_name
     *   The field name.
     *
     * @return \Drupal\Core\Field\PluginSettingsInterface|null
     *   A widget or formatter plugin or NULL if the field does not exist.
     */
    public function get_renderer($field_name);
    /**
     * Gets the entity type for which this display is used.
     *
     * @return string
     *   The entity type id.
     */
    public function get_target_entity_type_id();
    /**
     * Gets the view or form mode to be displayed.
     *
     * @return string
     *   The mode to be displayed.
     */
    public function get_mode();
    /**
     * Gets the original view or form mode that was requested.
     *
     * @return string
     *   The original mode that was requested.
     */
    public function get_original_mode();
    /**
     * Gets the bundle to be displayed.
     *
     * @return string
     *   The bundle to be displayed.
     */
    public function get_target_bundle();
    /**
     * Sets the bundle to be displayed.
     *
     * @param string $bundle
     *   The bundle to be displayed.
     *
     * @return $this
     */
    public function set_target_bundle($bundle);
}