<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Core\Entity\Content_Entity_Interface;
use Drupal\Core\Entity\Content_Entity_Type_Interface;
use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Event dispatched before an entity is exported as default content.
 *
 * Subscribers to this event can attach callback functions which can be used
 * to export specific fields or field types. When exporting fields that either
 * have that name, or match that data type, callback will be called for each
 * field item with two arguments: the field item, and an object which holds
 * metadata (e.g., dependencies) about the entity being exported. The callback
 * should return an array of exported values for that field item, or NULL if the
 * item should not be exported.
 *
 * Subscribers may also mark specific fields as either not exportable, or
 * as explicitly exportable -- for example, computed fields are not normally
 * exported, but a subscriber could flag a computed field as exportable if
 * circumstances require it.
 */
final class Pre_Export_Event extends Event
{
    /**
     * An array of export callbacks, keyed by field type.
     *
     * @var array<string, callable>
     */
    private array $callbacks = [];
    /**
     * Whether specific fields (keyed by name) should be exported or not.
     *
     * @var array<string, bool>
     */
    private array $allow_list = [];
    public function __construct(public readonly Content_Entity_Interface $entity, public readonly Export_Metadata $metadata)
    {
    }
    /**
     * Toggles whether a specific entity key should be exported.
     *
     * @param string $key
     *   An entity key, e.g. `uuid` or `langcode`. Can be a regular entity key, or
     *   a revision metadata key.
     * @param bool $export
     *   Whether to export the entity key, even if it is computed.
     */
    public function set_entity_key_exportable(string $key, bool $export = true): void
    {
        $entity_type = $this->entity->get_entity_type();
        assert($entity_type instanceof Content_Entity_Type_Interface);
        if ($entity_type->has_key($key)) {
            $this->set_exportable($entity_type->get_key($key), $export);
        } elseif ($entity_type->has_revision_metadata_key($key)) {
            $this->set_exportable($entity_type->get_revision_metadata_key($key), $export);
        }
    }
    /**
     * Toggles whether a specific field should be exported.
     *
     * @param string $name
     *   The name of the field.
     * @param bool $export
     *   Whether to export the field, even if it is computed.
     */
    public function set_exportable(string $name, bool $export = true): void
    {
        $this->allow_list[$name] = $export;
    }
    /**
     * Returns a map of which fields should be exported.
     *
     * @return bool[]
     *   An array whose keys are field names, and the values are booleans
     *   indicating whether the field should be exported, even if it is computed.
     */
    public function get_allow_list(): array
    {
        return $this->allow_list;
    }
    /**
     * Sets the export callback for a specific field name or data type.
     *
     * @param string $name_or_data_type
     *   A field name or field item data type, like `field_item:image`. If the
     *   callback should run for every field a given type, this should be prefixed
     *   with `field_item:`, which is the Typed Data prefix for field items. If
     *   there is no prefix, this is treated as a field name.
     * @param callable $callback
     *   The callback which should export items of the specified field type. See
     *   the class documentation for details.
     */
    public function set_callback(string $name_or_data_type, callable $callback): void
    {
        $this->callbacks[$name_or_data_type] = $callback;
    }
    /**
     * Returns the field export callbacks collected by this event.
     *
     * @return callable[]
     *   The export callbacks, keyed by field type.
     */
    public function get_callbacks(): array
    {
        return $this->callbacks;
    }
}