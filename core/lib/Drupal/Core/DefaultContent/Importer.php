<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Component\Plugin\Plugin_Inspection_Interface;
use Drupal\Core\Entity\Content_Entity_Interface;
use Drupal\Core\Entity\Entity_Repository_Interface;
use Drupal\Core\Entity\Entity_Type_Manager_Interface;
use Drupal\Core\Entity\Plugin\Data_Type\Entity_Reference;
use Drupal\Core\Field\Field_Item_Interface;
use Drupal\Core\File\File_System_Interface;
use Drupal\Core\Installer\Installer_Kernel;
use Drupal\Core\Language\Language_Manager_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\file\File_Interface;
use Drupal\link\Plugin\Field\Field_Type\Link_Item;
use Drupal\user\Entity_Owner_Interface;
use Psr\Log\Logger_Aware_Interface;
use Psr\Log\Logger_Aware_Trait;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * A service for handling import of content.
 *
 * @internal
 *   This API is experimental.
 */
final class Importer implements Logger_Aware_Interface
{
    use Logger_Aware_Trait;
    /**
     * The dependencies of the currently importing entity, if any.
     *
     * The keys are the UUIDs of the dependencies, and the values are arrays with
     * two members: the entity type ID of the dependency, and the UUID to load.
     *
     * @var array<string, string[]>|null
     */
    private ?array $dependencies = null;
    public function __construct(private readonly Entity_Type_Manager_Interface $entity_type_manager, private readonly Admin_Account_Switcher $account_switcher, private readonly File_System_Interface $file_system, private readonly Language_Manager_Interface $language_manager, private readonly Entity_Repository_Interface $entity_repository, private readonly Event_Dispatcher_Interface $event_dispatcher)
    {
    }
    /**
     * Imports content entities from disk.
     *
     * @param \Drupal\Core\DefaultContent\Finder $content
     *   The content finder, which has information on the entities to create
     *   in the necessary dependency order.
     * @param \Drupal\Core\DefaultContent\Existing $existing
     *   (optional) What to do if one of the entities being imported already
     *   exists, by UUID:
     *   - \Drupal\Core\DefaultContent\Existing::Error: Throw an exception.
     *   - \Drupal\Core\DefaultContent\Existing::Skip: Leave the existing entity
     *     as-is.
     * @param \Drupal\Core\Session\AccountInterface|null $account
     *   (optional) The account to use when importing the entities. Defaults to
     *   the administrator account.
     *
     * @throws \Drupal\Core\DefaultContent\ImportException
     *   - If any of the entities being imported are not content entities.
     *   - If any of the entities being imported already exists, by UUID, and
     *     $existing is \Drupal\Core\DefaultContent\Existing::Error.
     */
    public function import_content(Finder $content, Existing $existing = Existing::Error, ?Account_Interface $account = null): void
    {
        if (count($content->data) === 0) {
            return;
        }
        $event = new Pre_Import_Event($content, $existing);
        $skip = $this->event_dispatcher->dispatch($event)->get_skip_list();
        if ($account !== null) {
            $this->account_switcher->switch_to($account);
        } else {
            $account = $this->account_switcher->switch_to_administrator();
        }
        try {
            /** @var array{_meta: array<mixed>} $decoded */
            foreach ($content->data as $decoded) {
                ['uuid' => $uuid, 'entity_type' => $entity_type_id, 'path' => $path] = $decoded['_meta'];
                assert(is_string($uuid));
                assert(is_string($entity_type_id));
                assert(is_string($path));
                // The event subscribers asked to skip importing this entity. If they
                // explained why, log that.
                if (array_key_exists($uuid, $skip)) {
                    if ($skip[$uuid]) {
                        $this->logger?->info('Skipped importing @entity_type @uuid because: %reason', ['@entity_type' => $entity_type_id, '@uuid' => $uuid, '%reason' => $skip[$uuid]]);
                    }
                    continue;
                }
                $entity_type = $this->entity_type_manager->get_definition($entity_type_id);
                /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
                if (!$entity_type->entity_class_implements(Content_Entity_Interface::class)) {
                    throw new Import_Exception("Content entity {$uuid} is a '{$entity_type_id}', which is not a content entity type.");
                }
                $entity = $this->entity_repository->load_entity_by_uuid($entity_type_id, $uuid);
                if ($entity) {
                    if ($existing === Existing::Skip) {
                        continue;
                    }
                    throw new Import_Exception("{$entity_type_id} {$uuid} already exists.");
                }
                $entity = $this->to_entity($decoded)->enforce_is_new()->set_syncing(true);
                // Ensure that the entity is not owned by the anonymous user.
                if ($entity instanceof Entity_Owner_Interface && empty($entity->get_owner_id())) {
                    $entity->set_owner_id($account->id());
                }
                // If a file exists in the same folder, copy it to the designated
                // target URI.
                if ($entity instanceof File_Interface) {
                    $this->copy_file_associated_with_entity(dirname($path), $entity);
                }
                $violations = $entity->validate();
                if (count($violations) > 0) {
                    throw new Invalid_Entity_Exception($violations, $path);
                }
                $entity->save();
            }
        } finally {
            $this->account_switcher->switch_back();
        }
    }
    /**
     * Copies a file from default content directory to the site's file system.
     *
     * @param string $path
     *   The path to the file to copy.
     * @param \Drupal\file\FileInterface $entity
     *   The file entity.
     */
    private function copy_file_associated_with_entity(string $path, File_Interface &$entity): void
    {
        $destination = $entity->get_file_uri();
        assert(is_string($destination));
        // If the source file doesn't exist, there's nothing we can do.
        $source = $path . '/' . basename($destination);
        if (!file_exists($source)) {
            $this->logger?->warning('File entity %name was imported, but the associated file (@path) was not found.', ['%name' => $entity->label(), '@path' => $source]);
            return;
        }
        $copy_file = true;
        if (file_exists($destination)) {
            $source_hash = hash_file('sha256', $source);
            assert(is_string($source_hash));
            $destination_hash = hash_file('sha256', $destination);
            assert(is_string($destination_hash));
            if (hash_equals($source_hash, $destination_hash) && $this->entity_type_manager->get_storage('file')->load_by_properties(['uri' => $destination]) === []) {
                // If the file hashes match and the file is not already a managed file
                // then do not copy a new version to the file system. This prevents
                // re-installs during development from creating unnecessary duplicates.
                $copy_file = false;
            }
        }
        $scheme = parse_url($destination, PHP_URL_SCHEME);
        $target_directory = dirname($destination);
        if (!isset($scheme) || rtrim($target_directory, ':') !== $scheme) {
            $this->file_system->prepare_directory($target_directory, File_System_Interface::CREATE_DIRECTORY);
        }
        if ($copy_file) {
            $uri = $this->file_system->copy($source, $destination);
            $entity->set_file_uri($uri);
        }
    }
    /**
     * Converts an array of content entity data to a content entity object.
     *
     * @param array<string, array<mixed>> $data
     *   The entity data.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface
     *   The unsaved entity.
     *
     * @throws \Drupal\Core\DefaultContent\ImportException
     *   If the `entity_type` or `uuid` meta keys are not set.
     */
    private function to_entity(array $data): Content_Entity_Interface
    {
        if (empty($data['_meta']['entity_type'])) {
            throw new Import_Exception('The entity type metadata must be specified.');
        }
        if (empty($data['_meta']['uuid'])) {
            throw new Import_Exception('The uuid metadata must be specified.');
        }
        // Allow third-party code to modify the entity data before the entity is
        // created.
        $event = new Pre_Entity_Import_Event($data);
        $data = ['_meta' => $event->metadata] + $this->event_dispatcher->dispatch($event)->data;
        $is_root = false;
        // @see ::loadEntityDependency()
        if ($this->dependencies === null && !empty($data['_meta']['depends'])) {
            $is_root = true;
            foreach ($data['_meta']['depends'] as $uuid => $entity_type) {
                assert(is_string($uuid));
                assert(is_string($entity_type));
                $this->dependencies[$uuid] = [$entity_type, $uuid];
            }
        }
        ['entity_type' => $entity_type] = $data['_meta'];
        assert(is_string($entity_type));
        /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
        $entity_type = $this->entity_type_manager->get_definition($entity_type);
        $values = ['uuid' => $data['_meta']['uuid']];
        if (!empty($data['_meta']['bundle'])) {
            $values[$entity_type->get_key('bundle')] = $data['_meta']['bundle'];
        }
        if (!empty($data['_meta']['default_langcode'])) {
            $data = $this->verify_normalized_language($data);
            $values[$entity_type->get_key('langcode')] = $data['_meta']['default_langcode'];
        }
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $this->entity_type_manager->get_storage($entity_type->id())->create($values);
        foreach ($data['default'] as $field_name => $values) {
            $this->set_field_values($entity, $field_name, $values);
        }
        foreach ($data['translations'] ?? [] as $langcode => $translation_data) {
            if ($this->language_manager->get_language($langcode)) {
                $translation = $entity->add_translation($langcode, $entity->to_array());
                foreach ($translation_data as $field_name => $values) {
                    $this->set_field_values($translation, $field_name, $values);
                }
            }
        }
        if ($is_root) {
            $this->dependencies = null;
        }
        return $entity;
    }
    /**
     * Sets field values based on the normalized data.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The content entity.
     * @param string $field_name
     *   The name of the field.
     * @param array $values
     *   The normalized data for the field.
     */
    private function set_field_values(Content_Entity_Interface $entity, string $field_name, array $values): void
    {
        foreach ($values as $delta => $item_value) {
            if (!$entity->get($field_name)->get($delta)) {
                $entity->get($field_name)->append_item();
            }
            /** @var \Drupal\Core\Field\FieldItemInterface $item */
            $item = $entity->get($field_name)->get($delta);
            // Update the URI based on the target UUID for link fields.
            if (isset($item_value['target_uuid']) && $item instanceof Link_Item) {
                $target_entity = $this->load_entity_dependency($item_value['target_uuid']);
                if ($target_entity) {
                    $item_value['uri'] = 'entity:' . $target_entity->get_entity_type_id() . '/' . $target_entity->id();
                }
                unset($item_value['target_uuid']);
            }
            $serialized_property_names = self::get_custom_serialized_property_names($item);
            foreach ($item_value as $property_name => $value) {
                if (\in_array($property_name, $serialized_property_names)) {
                    if (\is_string($value)) {
                        throw new Import_Exception("Received string for serialized property {$field_name}.{$delta}.{$property_name}");
                    }
                    $value = serialize($value);
                }
                $property = $item->get($property_name);
                if ($property instanceof Entity_Reference) {
                    if (is_array($value)) {
                        $value = $this->to_entity($value);
                    } else {
                        $value = $this->load_entity_dependency($value);
                    }
                }
                $property->set_value($value);
            }
        }
    }
    /**
     * Gets the names of all properties the plugin treats as serialized data.
     *
     * This allows the field storage definition or entity type to provide a
     * setting for serialized properties. This can be used for fields that
     * handle serialized data themselves and do not rely on the serialized schema
     * flag.
     *
     * @param \Drupal\Core\Field\FieldItemInterface $field_item
     *   The field item.
     *
     * @return string[]
     *   The property names for serialized properties.
     *
     * @see \Drupal\serialization\Normalizer\SerializedColumnNormalizerTrait::getCustomSerializedPropertyNames
     */
    public static function get_custom_serialized_property_names(Field_Item_Interface $field_item): array
    {
        if ($field_item instanceof Plugin_Inspection_Interface) {
            $definition = $field_item->get_plugin_definition();
            $serialized_fields = $field_item->get_entity()->get_entity_type()->get('serialized_field_property_names');
            $field_name = $field_item->get_field_definition()->get_name();
            if (is_array($serialized_fields) && isset($serialized_fields[$field_name]) && is_array($serialized_fields[$field_name])) {
                return $serialized_fields[$field_name];
            }
            if (isset($definition['serialized_property_names']) && is_array($definition['serialized_property_names'])) {
                return $definition['serialized_property_names'];
            }
        }
        return [];
    }
    /**
     * Loads the entity dependency by its UUID.
     *
     * @param string $target_uuid
     *   The entity UUID.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface|null
     *   The loaded entity.
     */
    private function load_entity_dependency(string $target_uuid): ?Content_Entity_Interface
    {
        if ($this->dependencies && array_key_exists($target_uuid, $this->dependencies)) {
            $entity = $this->entity_repository->load_entity_by_uuid(...$this->dependencies[$target_uuid]);
            assert($entity instanceof Content_Entity_Interface || $entity === null);
            return $entity;
        }
        return null;
    }
    /**
     * Verifies that the site knows the default language of the normalized entity.
     *
     * Will attempt to switch to an alternative translation or just import it
     * with the site default language.
     *
     * @param array $data
     *   The normalized entity data.
     *
     * @return array
     *   The normalized entity data, possibly with altered default language
     *   and translations.
     */
    private function verify_normalized_language(array $data): array
    {
        $default_langcode = $data['_meta']['default_langcode'];
        $default_language = $this->language_manager->get_default_language();
        // Check the language. If the default language isn't known, import as one of
        // the available translations if one exists with those values. If none
        // exists, create the entity in the default language. During the installer,
        // when installing with an alternative language, `en` is still the default
        // when modules are installed so check the default language instead.
        if (!$this->language_manager->get_language($default_langcode) || Installer_Kernel::installation_attempted() && $default_language->get_id() !== $default_langcode) {
            $use_default = true;
            foreach ($data['translations'] ?? [] as $langcode => $translation_data) {
                if ($this->language_manager->get_language($langcode)) {
                    $data['_meta']['default_langcode'] = $langcode;
                    $data['default'] = \array_merge($data['default'], $translation_data);
                    unset($data['translations'][$langcode]);
                    $use_default = false;
                    break;
                }
            }
            if ($use_default) {
                $data['_meta']['default_langcode'] = $default_language->get_id();
            }
        }
        return $data;
    }
}