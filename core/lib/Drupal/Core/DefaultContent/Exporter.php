<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\Content_Entity_Interface;
use Drupal\Core\Entity\Entity_Repository_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Field\Field_Item_Interface;
use Drupal\Core\Field\Plugin\Field\Field_Type\Entity_Reference_Item_Interface;
use Drupal\Core\Field\Plugin\Field\Field_Type\Password_Item;
use Drupal\Core\File\Exception\Directory_Not_Ready_Exception;
use Drupal\Core\File\Exception\File_Exception;
use Drupal\Core\File\Exception\File_Write_Exception;
use Drupal\Core\File\File_Exists;
use Drupal\Core\File\File_System_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\Typed_Data\Primitive_Interface;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * Handles exporting content entities.
 *
 * @internal
 *   This API is experimental.
 */
final class Exporter implements Logger_Aware_Interface
{
    use Logger_Aware_Trait;
    public function __construct(private readonly Event_Dispatcher_Interface $event_dispatcher, private readonly File_System_Interface $file_system, private readonly Entity_Repository_Interface $entity_repository, private readonly Entity_Type_Manager_Interface $entity_type_manager)
    {
    }
    /**
     * Exports a single content entity as an array.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity to export.
     *
     * @return \Drupal\Core\DefaultContent\ExportResult
     *   A read-only value object with the exported entity data, and any metadata
     *   that was collected while exporting the entity, including dependencies and
     *   attachments.
     */
    public function export(Content_Entity_Interface $entity): Export_Result
    {
        $metadata = new Export_Metadata($entity);
        $event = new Pre_Export_Event($entity, $metadata);
        $field_definitions = $entity->get_field_definitions();
        // Ignore serial (integer) entity IDs by default, along with a number of
        // other keys that aren't useful for default content.
        if ($entity->get_entity_type()->has_integer_id()) {
            $event->set_entity_key_exportable('id', false);
        }
        $event->set_entity_key_exportable('uuid', false);
        $event->set_entity_key_exportable('revision', false);
        $event->set_entity_key_exportable('langcode', false);
        $event->set_entity_key_exportable('bundle', false);
        $event->set_entity_key_exportable('default_langcode', false);
        $event->set_entity_key_exportable('revision_default', false);
        $event->set_entity_key_exportable('revision_created', false);
        // Ignore fields that don't make sense in default content:
        // - `changed` fields aren't needed because default content has no history.
        // - `created` fields aren't needed because default content should be
        //   "created" upon import.
        foreach ($field_definitions as $name => $definition) {
            if (in_array($definition->get_type(), ['changed', 'created'], true)) {
                $event->set_exportable($name, false);
            }
        }
        // Exported user accounts should include the hashed password.
        $event->set_callback('field_item:password', fn(Password_Item $item): array => $item->set('pre_hashed', true)->get_value());
        // Ensure that all entity reference fields mark the referenced entity as a
        // dependency of the entity being exported.
        $event->set_callback('field_item:entity_reference', $this->export_reference(...));
        $event->set_callback('field_item:file', $this->export_reference(...));
        $event->set_callback('field_item:image', $this->export_reference(...));
        // Dispatch the event so modules can add and customize export callbacks, and
        // mark certain fields as ignored.
        $this->event_dispatcher->dispatch($event);
        $data = [];
        foreach ($entity->get_translation_languages() as $langcode => $language) {
            $translation = $entity->get_translation($langcode);
            $values = $this->export_translation($translation, $metadata, $event->get_callbacks(), $event->get_allow_list());
            if ($translation->is_default_translation()) {
                $data['default'] = $values;
            } else {
                $data['translations'][$langcode] = $values;
            }
        }
        return new Export_Result($data, $metadata);
    }
    /**
     * Exports an entity to a YAML file in a directory.
     *
     * Any attachments to the entity (e.g., physical files) will be copied into
     * the destination directory, alongside the exported entity.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity to export.
     * @param string $destination
     *   A destination path or URI; will be created if it does not exist. A
     *   subdirectory will be created for the entity type that is being exported.
     *
     * @return \Drupal\Core\DefaultContent\ExportResult
     *   The exported entity data and its metadata.
     */
    public function export_to_file(Content_Entity_Interface $entity, string $destination): Export_Result
    {
        $destination .= '/' . $entity->get_entity_type_id();
        // Ensure the destination directory exists and is writable.
        $this->file_system->prepare_directory($destination, File_System_Interface::CREATE_DIRECTORY | File_System_Interface::MODIFY_PERMISSIONS) || throw new Directory_Not_Ready_Exception("Could not create destination directory '{$destination}'");
        $destination = $this->file_system->realpath($destination);
        if (empty($destination)) {
            throw new File_Exception("Could not resolve the destination directory '{$destination}'");
        }
        $path = $destination . '/' . $entity->uuid() . '.' . Yaml::get_file_extension();
        $result = $this->export($entity);
        file_put_contents($path, (string) $result) || throw new File_Write_Exception("Could not write file '{$path}'");
        foreach ($result->metadata->get_attachments() as $from => $to) {
            $this->file_system->copy($from, $destination . '/' . $to, File_Exists::Replace);
        }
        return $result;
    }
    /**
     * Exports an entity and all of its dependencies to a directory.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity to export.
     * @param string $destination
     *   A destination path or URI; will be created if it does not exist.
     *   Subdirectories will be created for each entity type that is exported.
     *
     * @return int
     *   The number of entities that were exported.
     */
    public function export_with_dependencies(Content_Entity_Interface $entity, string $destination): int
    {
        $queue = [$entity];
        $done = [];
        while ($queue) {
            $entity = array_shift($queue);
            $uuid = $entity->uuid();
            // Don't export the same entity twice, both for performance and to prevent
            // an infinite loop caused by circular dependencies.
            if (isset($done[$uuid])) {
                continue;
            }
            $dependencies = $this->export_to_file($entity, $destination)->metadata->get_dependencies();
            foreach ($dependencies as $dependency) {
                $dependency = $this->entity_repository->load_entity_by_uuid(...$dependency);
                if ($dependency instanceof Content_Entity_Interface) {
                    $queue[] = $dependency;
                }
            }
            $done[$uuid] = true;
        }
        return count($done);
    }
    /**
     * Exports a single translation of a content entity.
     *
     * Any fields that are explicitly marked non-exportable (including computed
     * properties by default) will not be exported.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $translation
     *   The translation to export.
     * @param \Drupal\Core\DefaultContent\ExportMetadata $metadata
     *   Any metadata about the entity being exported (e.g., dependencies).
     * @param callable[] $callbacks
     *   Custom export functions for specific field types, keyed by field type.
     * @param array<string, bool> $allow_list
     *   An array of booleans that indicate whether a specific field should be
     *   exported or not, even if it is computed. Keyed by field name.
     *
     * @return array
     *   The exported translation.
     */
    private function export_translation(Content_Entity_Interface $translation, Export_Metadata $metadata, array $callbacks, array $allow_list): array
    {
        $data = [];
        foreach ($translation->get_fields() as $name => $items) {
            // Skip the field if it's empty, or it was explicitly disallowed, or is a
            // computed field that wasn't explicitly allowed.
            $allowed = $allow_list[$name] ?? null;
            if ($allowed === false) {
                continue;
            }
            if ($allowed === null && $items->get_data_definition()->is_computed()) {
                continue;
            }
            if ($items->is_empty()) {
                continue;
            }
            // Try to find a callback for this specific field, then for the field's
            // data type, and finally fall back to a generic callback.
            $data_type = $items->get_field_definition()->get_item_definition()->get_data_type();
            $callback = $callbacks[$name] ?? $callbacks[$data_type] ?? $this->export_field_item(...);
            /** @var \Drupal\Core\Field\FieldItemInterface $item */
            foreach ($items as $item) {
                $values = $callback($item, $metadata);
                // If the callback returns NULL, this item should not be exported.
                if (is_array($values)) {
                    $data[$name][] = $values;
                }
            }
        }
        return $data;
    }
    /**
     * Exports a single field item generically.
     *
     * Any properties of the item that are explicitly marked non-exportable (which
     * includes computed properties by default) will not be exported.
     *
     * Field types that need special handling should provide a custom callback
     * function to the exporter by subscribing to
     * \Drupal\Core\DefaultContent\PreExportEvent.
     *
     * @param \Drupal\Core\Field\FieldItemInterface $item
     *   The field item to export.
     *
     * @return array
     *   The exported field values.
     *
     * @see \Drupal\Core\DefaultContent\PreExportEvent::setCallback()
     */
    private function export_field_item(Field_Item_Interface $item): array
    {
        $custom_serialized = Importer::get_custom_serialized_property_names($item);
        $values = [];
        foreach ($item->get_properties() as $name => $property) {
            $value = $property instanceof Primitive_Interface ? $property->get_casted_value() : $property->get_value();
            if (is_string($value) && in_array($name, $custom_serialized, true)) {
                $value = unserialize($value);
            }
            $values[$name] = $value;
        }
        return $values;
    }
    /**
     * Exports an entity reference field item.
     *
     * @param \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface&\Drupal\Core\Field\FieldItemInterface $item
     *   The field item to export.
     * @param \Drupal\Core\DefaultContent\ExportMetadata $metadata
     *   Any metadata about the entity being exported (e.g., dependencies).
     *
     * @return array|null
     *   The exported field values, or NULL if no entity is referenced and the
     *   item should not be exported.
     */
    private function export_reference(Entity_Reference_Item_Interface&Field_Item_Interface $item, Export_Metadata $metadata): ?array
    {
        $entity = $item->get('entity')->get_value();
        // No entity is referenced, so there's nothing else we can do here.
        if ($entity === null) {
            $referencer = $item->get_entity();
            $field_definition = $item->get_field_definition();
            $this->logger?->warning('Failed to export reference to @target_type %missing_id referenced by %field on @entity_type %label because the referenced @target_type does not exist.', ['@target_type' => (string) $this->entity_type_manager->get_definition($field_definition->get_field_storage_definition()->get_setting('target_type'))->get_singular_label(), '%missing_id' => $item->get('target_id')->get_value(), '%field' => $field_definition->get_label(), '@entity_type' => (string) $referencer->get_entity_type()->get_singular_label(), '%label' => $referencer->label()]);
            return null;
        }
        $values = $this->export_field_item($item);
        if ($entity instanceof Content_Entity_Interface) {
            // If the referenced entity is user 0 or 1, we can skip further
            // processing because user 0 is guaranteed to exist, and user 1 is
            // guaranteed to have existed at some point. Either way, there's no chance
            // of accidentally referencing the wrong entity on import.
            if ($entity instanceof Account_Interface && intval($entity->id()) < 2) {
                return array_map(intval(...), $values);
            }
            // Mark the referenced entity as a dependency of the one we're exporting.
            $metadata->add_dependency($entity);
            // If the referenced entity ID is numeric, refer to it by UUID, which is
            // portable. If the ID isn't numeric, assume it's meant to be consistent
            // (like a config entity ID) and leave the reference as-is. Workspaces
            // are an example of an entity type that should be treated this way.
            if ($entity->get_entity_type()->has_integer_id()) {
                $values['entity'] = $entity->uuid();
                unset($values['target_id']);
            }
        }
        return $values;
    }
}