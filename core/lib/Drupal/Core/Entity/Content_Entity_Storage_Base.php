<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Memory_Cache\Memory_Cache_Interface;
use Drupal\Core\Entity\Exception\Ambiguous_Bundle_Class_Exception;
use Drupal\Core\Entity\Exception\Bundle_Class_Inheritance_Exception;
use Drupal\Core\Entity\Exception\Missing_Bundle_Class_Exception;
use Drupal\Core\Field\Field_Definition_Interface;
use Drupal\Core\Field\Field_Storage_Definition_Interface;
use Drupal\Core\Language\Language_Interface;
use Drupal\Core\Typed_Data\Translation_Status_Interface;
use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * Base class for content entity storage handlers.
 */
abstract class Content_Entity_Storage_Base extends Entity_Storage_Base implements Content_Entity_Storage_Interface, Dynamically_Fieldable_Entity_Storage_Interface, Bundle_Entity_Storage_Interface
{
    /**
     * The entity bundle key.
     *
     * @var string|bool
     */
    protected $bundle_key = false;
    /**
     * Whether the static revision cache should be ignored.
     *
     * This property will be set internally when loading an unchanged revision
     * via ::loadRevisionUnchanged() before calling ::loadRevision() to load the
     * revision without using the static revision cache.
     *
     *
     * @see \Drupal\Core\Entity\ContentEntityStorageBase::loadRevisionUnchanged()
     * @see \Drupal\Core\Entity\ContentEntityStorageBase::loadMultipleRevisions()
     */
    protected bool $ignore_static_revision_cache = false;
    /**
     * Constructs a ContentEntityStorageBase object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
     *   The entity field manager.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
     *   The cache backend to be used.
     * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
     *   The memory cache backend.
     * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
     *   The entity type bundle info.
     */
    public function __construct(Entity_Type_Interface $entity_type, protected \Drupal\Core\Entity\Entity_Field_Manager_Interface $entity_field_manager, protected \Drupal\Core\Cache\Cache_Backend_Interface $cache_backend, Memory_Cache_Interface $memory_cache, protected \Drupal\Core\Entity\Entity_Type_Bundle_Info_Interface $entity_type_bundle_info)
    {
        parent::__construct($entity_type, $memory_cache);
        $this->bundle_key = $this->entity_type->get_key('bundle');
    }
    /**
     * {@inheritdoc}
     */
    public function create(array $values = [])
    {
        $bundle = $this->get_bundle_from_values($values);
        $entity_class = $this->get_entity_class($bundle);
        // @todo Decide what to do if preCreate() tries to change the bundle.
        // @see https://www.drupal.org/project/drupal/issues/3230792
        $entity_class::pre_create($this, $values);
        // Assign a new UUID if there is none yet.
        if ($this->uuid_key && $this->uuid_service && !isset($values[$this->uuid_key])) {
            $values[$this->uuid_key] = $this->uuid_service->generate();
        }
        $entity = $this->do_create($values);
        $entity->enforce_is_new();
        $entity->post_create($this);
        // Modules might need to add or change the data initially held by the new
        // entity object, for instance to fill-in default values.
        $this->invoke_hook('create', $entity);
        return $entity;
    }
    /**
     * {@inheritdoc}
     */
    public static function create_instance(Container_Interface $container, Entity_Type_Interface $entity_type)
    {
        return new static($entity_type, $container->get('entity_field.manager'), $container->get('cache.entity'), $container->get('entity.memory_cache'), $container->get('entity_type.bundle.info'));
    }
    /**
     * {@inheritdoc}
     */
    protected function do_create(array $values)
    {
        $bundle = $this->get_bundle_from_values($values);
        if ($this->bundle_key && !$bundle) {
            throw new Entity_Storage_Exception('Missing bundle for entity type ' . $this->entity_type_id);
        }
        $entity_class = $this->get_entity_class($bundle);
        $entity = new $entity_class([], $this->entity_type_id, $bundle);
        $this->init_field_values($entity, $values);
        return $entity;
    }
    /**
     * {@inheritdoc}
     */
    public function get_bundle_from_class(string $class_name): ?string
    {
        $bundle_for_class = null;
        foreach ($this->entity_type_bundle_info->get_bundle_info($this->entity_type_id) as $bundle => $bundle_info) {
            if (!empty($bundle_info['class']) && $bundle_info['class'] === $class_name) {
                if ($bundle_for_class) {
                    throw new Ambiguous_Bundle_Class_Exception($class_name);
                }
                $bundle_for_class = $bundle;
            }
        }
        return $bundle_for_class;
    }
    /**
     * Retrieves the bundle from an array of values.
     *
     * @param array $values
     *   An array of values to set, keyed by field name.
     *
     * @return string|null
     *   The bundle or NULL if not set.
     */
    protected function get_bundle_from_values(array $values): ?string
    {
        $bundle = null;
        // Make sure we have a reasonable bundle key. If not, bail early.
        if (!$this->bundle_key || !isset($values[$this->bundle_key])) {
            return null;
        }
        // Normalize the bundle value. This is an optimized version of
        // \Drupal\Core\Field\FieldInputValueNormalizerTrait::normalizeValue()
        // because we just need the scalar value.
        $bundle_value = $values[$this->bundle_key];
        if (!is_array($bundle_value)) {
            // The bundle value is a scalar, use it as-is.
            $bundle = $bundle_value;
        } elseif (is_numeric(array_keys($bundle_value)[0])) {
            // The bundle value is a field item list array, keyed by delta.
            $bundle = reset($bundle_value[0]);
        } else {
            // The bundle value is a field item array, keyed by the field's main
            // property name.
            $bundle = reset($bundle_value);
        }
        return $bundle;
    }
    /**
     * {@inheritdoc}
     */
    public function get_entity_class(?string $bundle = null): string
    {
        $entity_class = parent::get_entity_class();
        // If no bundle is set, use the entity type ID as the bundle ID.
        $bundle ??= $this->get_entity_type_id();
        // Return the bundle class if it has been defined for this bundle.
        $bundle_info = $this->entity_type_bundle_info->get_bundle_info($this->entity_type_id);
        $bundle_class = $bundle_info[$bundle]['class'] ?? null;
        // Bundle classes should exist and extend the main entity class.
        if ($bundle_class) {
            if (!class_exists($bundle_class)) {
                throw new Missing_Bundle_Class_Exception($bundle_class);
            }
            if (!is_subclass_of($bundle_class, $entity_class)) {
                throw new Bundle_Class_Inheritance_Exception($bundle_class, $entity_class);
            }
            return $bundle_class;
        }
        return $entity_class;
    }
    /**
     * {@inheritdoc}
     */
    public function create_with_sample_values($bundle = false, array $values = [])
    {
        // ID and revision should never have sample values generated for them.
        $forbidden_keys = [$this->entity_type->get_key('id')];
        if ($revision_key = $this->entity_type->get_key('revision')) {
            $forbidden_keys[] = $revision_key;
        }
        if ($bundle_key = $this->entity_type->get_key('bundle')) {
            if (!$bundle) {
                throw new Entity_Storage_Exception('No entity bundle was specified');
            }
            if (!array_key_exists($bundle, $this->entity_type_bundle_info->get_bundle_info($this->entity_type_id))) {
                throw new Entity_Storage_Exception(sprintf('Missing entity bundle. The "%s" bundle does not exist', $bundle));
            }
            $values[$bundle_key] = $bundle;
            // Bundle is already set.
            $forbidden_keys[] = $bundle_key;
        }
        // Forbid sample generation on any keys whose values were submitted.
        $forbidden_keys = array_merge($forbidden_keys, array_keys($values));
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
        $entity = $this->create($values);
        foreach ($entity as $field_name => $value) {
            if (!in_array($field_name, $forbidden_keys, true)) {
                $entity->get($field_name)->generate_sample_items();
            }
        }
        return $entity;
    }
    /**
     * Initializes field values.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   An entity object.
     * @param array $values
     *   (optional) An associative array of initial field values keyed by field
     *   name. If none is provided default values will be applied.
     * @param array $field_names
     *   (optional) An associative array of field names to be initialized. If none
     *   is provided all fields will be initialized.
     */
    protected function init_field_values(Content_Entity_Interface $entity, array $values = [], array $field_names = [])
    {
        // Populate field values.
        foreach ($entity as $name => $field) {
            if (!$field_names || isset($field_names[$name])) {
                if (isset($values[$name])) {
                    $entity->{$name} = $values[$name];
                } elseif (!array_key_exists($name, $values)) {
                    $entity->get($name)->apply_default_value();
                }
            }
            unset($values[$name]);
        }
        // Set any passed values for non-defined fields also.
        foreach ($values as $name => $value) {
            $entity->{$name} = $value;
        }
        // Make sure modules can alter field initial values.
        $this->invoke_hook('field_values_init', $entity);
    }
    /**
     * Checks whether any entity revision is translated.
     *
     * @param \Drupal\Core\Entity\TranslatableInterface $entity
     *   The entity object to be checked.
     *
     * @return bool
     *   TRUE if the entity has at least one translation in any revision, FALSE
     *   otherwise.
     *
     * @see \Drupal\Core\TypedData\TranslatableInterface::getTranslationLanguages()
     * @see \Drupal\Core\Entity\ContentEntityStorageBase::isAnyStoredRevisionTranslated()
     */
    protected function is_any_revision_translated(Translatable_Interface $entity)
    {
        if ($entity->get_translation_languages(false)) {
            return true;
        }
        return $this->is_any_stored_revision_translated($entity);
    }
    /**
     * Checks whether any stored entity revision is translated.
     *
     * A revisionable entity can have translations in a pending revision, hence
     * the default revision may appear as not translated. This determines whether
     * the entity has any translation in the storage and thus should be considered
     * as multilingual.
     *
     * @param \Drupal\Core\Entity\TranslatableInterface $entity
     *   The entity object to be checked.
     *
     * @return bool
     *   TRUE if the entity has at least one translation in any revision, FALSE
     *   otherwise.
     *
     * @see \Drupal\Core\TypedData\TranslatableInterface::getTranslationLanguages()
     * @see \Drupal\Core\Entity\ContentEntityStorageBase::isAnyRevisionTranslated()
     */
    protected function is_any_stored_revision_translated(Translatable_Interface $entity)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        if ($entity->is_new()) {
            return false;
        }
        if ($entity instanceof Translation_Status_Interface) {
            foreach ($entity->get_translation_languages(false) as $langcode => $language) {
                if ($entity->get_translation_status($langcode) === Translation_Status_Interface::TRANSLATION_EXISTING) {
                    return true;
                }
            }
        }
        $query = $this->get_query()->condition($this->entity_type->get_key('id'), $entity->id())->condition($this->entity_type->get_key('default_langcode'), 0)->access_check(false)->range(0, 1);
        if ($entity->get_entity_type()->is_revisionable()) {
            $query->all_revisions();
        }
        $result = $query->execute();
        return !empty($result);
    }
    /**
     * {@inheritdoc}
     */
    public function create_translation(Content_Entity_Interface $entity, $langcode, array $values = [])
    {
        $translation = $entity->get_translation($langcode);
        $definitions = array_filter($translation->get_field_definitions(), fn(Field_Definition_Interface $definition) => $definition->is_translatable());
        $field_names = array_map(fn(Field_Definition_Interface $definition) => $definition->get_name(), $definitions);
        $values[$this->langcode_key] = $langcode;
        $values[$this->get_entity_type()->get_key('default_langcode')] = false;
        $this->init_field_values($translation, $values, $field_names);
        $this->invoke_hook('translation_create', $translation);
        return $translation;
    }
    /**
     * {@inheritdoc}
     */
    public function create_revision(Revisionable_Interface $entity, $default = true, $keep_untranslatable_fields = null)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $new_revision = clone $entity;
        $original_keep_untranslatable_fields = $keep_untranslatable_fields;
        // For translatable entities, create a merged revision of the active
        // translation and the other translations in the default revision. This
        // permits the creation of pending revisions that can always be saved as the
        // new default revision without reverting changes in other languages.
        if (!$entity->is_new() && !$entity->is_default_revision() && $entity->is_translatable() && $this->is_any_revision_translated($entity)) {
            $active_langcode = $entity->language()->get_id();
            $skipped_field_names = array_flip($this->get_revision_translation_merge_skipped_field_names());
            // By default we copy untranslatable field values from the default
            // revision, unless they are configured to affect only the default
            // translation. This way we can ensure we always have only one affected
            // translation in pending revisions. This constraint is enforced by
            // EntityUntranslatableFieldsConstraintValidator.
            if (!isset($keep_untranslatable_fields)) {
                $keep_untranslatable_fields = $entity->is_default_translation() && $entity->is_default_translation_affected_only();
            }
            /** @var \Drupal\Core\Entity\ContentEntityInterface $default_revision */
            $default_revision = $this->load($entity->id());
            $translation_languages = $default_revision->get_translation_languages();
            foreach ($translation_languages as $langcode => $language) {
                if ($langcode == $active_langcode) {
                    continue;
                }
                $default_revision_translation = $default_revision->get_translation($langcode);
                $new_revision_translation = $new_revision->has_translation($langcode) ? $new_revision->get_translation($langcode) : $new_revision->add_translation($langcode);
                /** @var \Drupal\Core\Field\FieldItemListInterface[] $sync_items */
                $sync_items = array_diff_key($keep_untranslatable_fields ? $default_revision_translation->get_translatable_fields() : $default_revision_translation->get_fields(), $skipped_field_names);
                foreach ($sync_items as $field_name => $items) {
                    $new_revision_translation->set($field_name, $items->get_value());
                }
                // Make sure the "revision_translation_affected" flag is recalculated.
                $new_revision_translation->set_revision_translation_affected(null);
                // No need to copy untranslatable field values more than once.
                $keep_untranslatable_fields = true;
            }
            // Make sure we do not inadvertently recreate removed translations.
            foreach (array_diff_key($new_revision->get_translation_languages(), $translation_languages) as $langcode => $language) {
                // Allow a new revision to be created for the active language.
                if ($langcode !== $active_langcode) {
                    $new_revision->remove_translation($langcode);
                }
            }
            // The "original" property is used in various places to detect changes in
            // field values with respect to the stored ones. If the property is not
            // defined, the stored version is loaded explicitly. Since the merged
            // revision generated here is not stored anywhere, we need to populate the
            // "original" property manually, so that changes can be properly detected.
            $new_revision->set_original(clone $new_revision);
        }
        // Eventually mark the new revision as such.
        $new_revision->set_new_revision();
        $new_revision->is_default_revision($default);
        // Actually make sure the current translation is marked as affected, even if
        // there are no explicit changes, to be sure this revision can be related
        // to the correct translation.
        $new_revision->set_revision_translation_affected(true);
        // Notify modules about the new revision.
        $arguments = [$new_revision, $entity, $original_keep_untranslatable_fields];
        $this->module_handler()->invoke_all($this->entity_type_id . '_revision_create', $arguments);
        $this->module_handler()->invoke_all('entity_revision_create', $arguments);
        return $new_revision;
    }
    /**
     * Returns an array of field names to skip when merging revision translations.
     *
     * @return array
     *   An array of field names.
     */
    protected function get_revision_translation_merge_skipped_field_names()
    {
        /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
        $entity_type = $this->get_entity_type();
        // A list of known revision metadata fields which should be skipped from
        // the comparison.
        $field_names = [$entity_type->get_key('revision'), $entity_type->get_key('revision_translation_affected')];
        return array_merge($field_names, array_values($entity_type->get_revision_metadata_keys()));
    }
    /**
     * {@inheritdoc}
     */
    public function get_latest_revision_id($entity_id)
    {
        if (!$this->entity_type->is_revisionable()) {
            return null;
        }
        $cached = $this->memory_cache->get("latest_revision_id:{$this->entity_type_id}:{$entity_id}");
        $latest_revision_ids = $cached ? $cached->data : [];
        if (!isset($latest_revision_ids[Language_Interface::LANGCODE_DEFAULT])) {
            $result = $this->get_query()->latest_revision()->condition($this->entity_type->get_key('id'), $entity_id)->access_check(false)->execute();
            $latest_revision_ids[Language_Interface::LANGCODE_DEFAULT] = key($result);
            $this->memory_cache->set("latest_revision_id:{$this->entity_type_id}:{$entity_id}", $latest_revision_ids, Memory_Cache_Interface::CACHE_PERMANENT, [$this->memory_cache_tag]);
        }
        return $latest_revision_ids[Language_Interface::LANGCODE_DEFAULT];
    }
    /**
     * {@inheritdoc}
     */
    public function get_latest_translation_affected_revision_id($entity_id, $langcode)
    {
        if (!$this->entity_type->is_revisionable()) {
            return null;
        }
        if (!$this->entity_type->is_translatable()) {
            return $this->get_latest_revision_id($entity_id);
        }
        $cached = $this->memory_cache->get("latest_revision_id:{$this->entity_type_id}:{$entity_id}");
        $latest_revision_ids = $cached ? $cached->data : [];
        if (!isset($latest_revision_ids[$langcode])) {
            $result = $this->get_query()->all_revisions()->condition($this->entity_type->get_key('id'), $entity_id)->condition($this->entity_type->get_key('revision_translation_affected'), 1, '=', $langcode)->range(0, 1)->sort($this->entity_type->get_key('revision'), 'DESC')->access_check(false)->add_meta_data('entity_id', $entity_id)->add_tag('latest_translated_affected_revision')->execute();
            $latest_revision_ids[$langcode] = key($result);
            $this->memory_cache->set("latest_revision_id:{$this->entity_type_id}:{$entity_id}", $latest_revision_ids, Memory_Cache_Interface::CACHE_PERMANENT, [$this->memory_cache_tag]);
        }
        return $latest_revision_ids[$langcode];
    }
    /**
     * {@inheritdoc}
     */
    public function on_field_storage_definition_create(Field_Storage_Definition_Interface $storage_definition)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function on_field_storage_definition_update(Field_Storage_Definition_Interface $storage_definition, Field_Storage_Definition_Interface $original)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function on_field_storage_definition_delete(Field_Storage_Definition_Interface $storage_definition)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function on_field_definition_create(Field_Definition_Interface $field_definition)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function on_field_definition_update(Field_Definition_Interface $field_definition, Field_Definition_Interface $original)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function on_field_definition_delete(Field_Definition_Interface $field_definition)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function purge_field_data(Field_Definition_Interface $field_definition, $batch_size)
    {
        $items_by_entity = $this->read_field_items_to_purge($field_definition, $batch_size);
        foreach ($items_by_entity as $items) {
            $items->delete();
            $this->purge_field_items($items->get_entity(), $field_definition);
        }
        return count($items_by_entity);
    }
    /**
     * Reads values to be purged for a single field.
     *
     * This method is called during field data purge, on fields for which
     * onFieldDefinitionDelete() has previously run.
     *
     * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     *   The field definition.
     * @param int $batch_size
     *   The maximum number of field data records to purge before returning.
     *
     * @return array<int,\Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
     *   An array of field item lists, keyed by entity revision id.
     */
    abstract protected function read_field_items_to_purge(Field_Definition_Interface $field_definition, $batch_size);
    /**
     * Removes field items from storage per entity during purge.
     *
     * @param ContentEntityInterface $entity
     *   The entity revision, whose values are being purged.
     * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     *   The field whose values are bing purged.
     */
    abstract protected function purge_field_items(Content_Entity_Interface $entity, Field_Definition_Interface $field_definition);
    /**
     * {@inheritdoc}
     */
    public function finalize_purge(Field_Storage_Definition_Interface $storage_definition)
    {
    }
    /**
     * {@inheritdoc}
     */
    protected function pre_load(?array &$ids = null)
    {
        $entities = [];
        // Call hook_entity_preload().
        $preload_ids = $ids ?: [];
        $preload_entities = $this->module_handler()->invoke_all('entity_preload', [$preload_ids, $this->entity_type_id]);
        foreach ((array) $preload_entities as $entity) {
            $entities[$entity->id()] = $entity;
        }
        if ($entities) {
            // If any entities were pre-loaded, remove them from the IDs still to
            // load.
            if ($ids !== null) {
                $ids = array_keys(array_diff_key(array_flip($ids), $entities));
            } else {
                $result = $this->get_query()->access_check(false)->condition($this->entity_type->get_key('id'), array_keys($entities), 'NOT IN')->execute();
                $ids = array_values($result);
            }
        }
        return $entities;
    }
    /**
     * {@inheritdoc}
     */
    public function load_revision($revision_id)
    {
        $revisions = $this->load_multiple_revisions([$revision_id]);
        return $revisions[$revision_id] ?? null;
    }
    /**
     * {@inheritdoc}
     */
    public function load_multiple_revisions(array $revision_ids)
    {
        $revisions = [];
        // Create a new variable which is a prepared version of the
        // $revision_ids array for later comparison with the revision cache. The
        // $revision_ids array is reduced as items are loaded from cache, allowing
        // storage queries to be avoided.
        $flipped_revision_ids = array_flip($revision_ids);
        // Try to load entities from the static cache.
        if (!$this->ignore_static_revision_cache && $revision_ids) {
            $revisions += $this->get_from_static_revision_cache($revision_ids);
            // If any revisions were loaded, remove them from the ids still to load.
            if ($flipped_revision_ids && $revisions) {
                $revision_ids = array_keys(array_diff_key($flipped_revision_ids, $revisions));
            }
        }
        // Attempt to load entities from the persistent cache. This will remove IDs
        // that were loaded from $revision_ids.
        $persistent_cache_revisions = $this->get_from_persistent_revision_cache($revision_ids);
        // Invoke post load on those revisions.
        foreach ($this->get_grouped_entities_from_revisions($persistent_cache_revisions) as $entities) {
            $this->post_load($entities);
        }
        $revisions += $persistent_cache_revisions;
        // Load any remaining revisions from the storage. This is the case if there
        // are any revision IDs left to load.
        $queried_revisions = [];
        if ($revision_ids) {
            $queried_revisions = $this->do_load_multiple_revisions_field_items($revision_ids);
            // Pass all revisions loaded from the database through $this->postLoad(),
            // which attaches fields (if supported by the entity type) and calls the
            // entity type specific load callback, for example hook_node_load().
            if ($queried_revisions) {
                $entity_groups = $this->get_grouped_entities_from_revisions($queried_revisions);
                // Invoke the entity hooks for each group, store the entities in the
                // persistent cache between the storage load hooks and the regular post
                // load processing.
                foreach ($entity_groups as $entities) {
                    $this->invoke_storage_load_hook($entities);
                }
                $this->set_persistent_revision_cache($queried_revisions);
                foreach ($entity_groups as $entities) {
                    $this->post_load($entities);
                }
                $revisions += $queried_revisions;
            }
        }
        if (!$this->ignore_static_revision_cache && $this->entity_type->is_statically_cacheable()) {
            // Add revisions to the static cache, but only with the revision ID.
            // This avoids issues with entity preloading as that might not
            // result in actually being the default revision.
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            foreach ($queried_revisions + $persistent_cache_revisions as $entity) {
                $id = $entity->id();
                // @see \Drupal\Core\Entity\ContentEntityStorageBase::setPersistentCache()
                $cache_tags_revision = [$this->memory_cache_tag, "{$this->entity_type_id}:{$id}:revisions"];
                $this->memory_cache->set($this->build_revision_cache_id($entity->get_revision_id()), $entity, Memory_Cache_Interface::CACHE_PERMANENT, $cache_tags_revision);
            }
        }
        // Ensure that the returned array is ordered the same as the original
        // $revision_ids array if this was passed in and remove any invalid revision
        // IDs.
        if ($flipped_revision_ids) {
            // Remove any invalid revision IDs from the array.
            $flipped_revision_ids = array_intersect_key($flipped_revision_ids, $revisions);
            foreach ($revisions as $revision_id => $revision) {
                $flipped_revision_ids[$revision_id] = $revision;
            }
            $revisions = $flipped_revision_ids;
        }
        return $revisions;
    }
    /**
     * Splits revisions into groups which are keyed by entity ID.
     *
     * Load hooks expect entities to be grouped by entity ID. As we could load
     * multiple revisions for the same entity ID at once we have to build
     * groups of entities where the same entity ID is present only once.
     *
     * Given 3 revisions, 1 and 2 for entity ID 1 and revision 3 for entity ID 2,
     * it will return the following two groups:
     *
     * @code
     * $entity_groups = [
     *   0 => [
     *     1 => $revision_1,
     *     2 => $revision_3,
     *   ],
     *   1 => [
     *     1 => $revision_2,
     *   ],
     * ];
     * @endcode
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface[] $revisions
     *   List of revisions.
     *
     * @return array<int, array<int, \Drupal\Core\Entity\ContentEntityInterface>>
     *   Groups of entities keyed by entity ID.
     */
    protected function get_grouped_entities_from_revisions(array $revisions): array
    {
        $entity_groups = [];
        $entity_group_mapping = [];
        foreach ($revisions as $revision) {
            $entity_id = $revision->id();
            $entity_group_key = isset($entity_group_mapping[$entity_id]) ? $entity_group_mapping[$entity_id] + 1 : 0;
            $entity_group_mapping[$entity_id] = $entity_group_key;
            $entity_groups[$entity_group_key][$entity_id] = $revision;
        }
        return $entity_groups;
    }
    /**
     * {@inheritdoc}
     */
    public function load_revision_unchanged($revision_id): ?Entity_Interface
    {
        // Load the revision by ignoring the static entity revision cache.
        $revision_ids = [$revision_id];
        $revisions = $this->get_from_persistent_revision_cache($revision_ids);
        if ($revisions) {
            $revision = $revisions[$revision_id];
            $entities = [$revision->id() => $revision];
            $this->post_load($entities);
        } else {
            $this->ignore_static_revision_cache = true;
            $revision = $this->load_revision($revision_id);
            $this->ignore_static_revision_cache = false;
        }
        return $revision;
    }
    /**
     * Actually loads revision field item values from the storage.
     *
     * @param array $revision_ids
     *   An array of revision identifiers.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface[]
     *   The specified entity revisions or an empty array if none are found.
     */
    abstract protected function do_load_multiple_revisions_field_items($revision_ids);
    /**
     * {@inheritdoc}
     */
    protected function do_save($id, Entity_Interface $entity)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        if ($entity->is_new()) {
            // Ensure the entity is still seen as new after assigning it an id, while
            // storing its data.
            $entity->enforce_is_new();
            if ($this->entity_type->is_revisionable()) {
                $entity->set_new_revision();
            }
            $return = SAVED_NEW;
        } else {
            // @todo Consider returning a different value when saving a non-default
            //   entity revision. See https://www.drupal.org/node/2509360.
            $return = $entity->is_default_revision() ? SAVED_UPDATED : false;
        }
        $this->populate_affected_revision_translations($entity);
        // Populate the "revision_default" flag. Skip this when we are resaving
        // the revision, and the flag is set to FALSE, since it is not possible to
        // set a previously default revision to non-default. However, setting a
        // previously non-default revision to default is allowed for advanced
        // use-cases.
        if ($this->entity_type->is_revisionable() && ($entity->is_new_revision() || $entity->is_default_revision())) {
            $revision_default_key = $this->entity_type->get_revision_metadata_key('revision_default');
            $entity->set($revision_default_key, $entity->is_default_revision());
        }
        $this->do_save_field_items($entity);
        return $return;
    }
    /**
     * Writes entity field values to the storage.
     *
     * This method is responsible for allocating entity and revision identifiers
     * and updating the entity object with their values.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity object.
     * @param string[] $names
     *   (optional) The name of the fields to be written to the storage. If an
     *   empty value is passed all field values are saved.
     */
    abstract protected function do_save_field_items(Content_Entity_Interface $entity, array $names = []);
    /**
     * {@inheritdoc}
     */
    protected function do_pre_save(Entity_Interface $entity)
    {
        /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
        // Sync the changes made in the fields array to the internal values array.
        $entity->update_original_values();
        if ($entity->get_entity_type()->is_revisionable() && !$entity->is_new() && empty($entity->get_loaded_revision_id())) {
            // Update the loaded revision id for rare special cases when no loaded
            // revision is given when updating an existing entity. This for example
            // happens when calling save() in hook_entity_insert().
            $entity->update_loaded_revision_id();
        }
        // Use the loaded revision instead of default one to check for data change.
        if (!$entity->is_new() && !$entity->get_original() && !$entity->was_default_revision()) {
            $original = $this->load_revision_unchanged($entity->get_loaded_revision_id());
            $entity->set_original($original);
        }
        $id = parent::do_pre_save($entity);
        $previously_default_revision = $entity->was_default_revision();
        $no_longer_default = !$entity->is_default_revision();
        $original_same_as_current = $entity->get_original()?->get_revision_id() == $entity->get_loaded_revision_id();
        $not_new_revision = !$entity->is_new_revision();
        if ($previously_default_revision && $no_longer_default && $original_same_as_current && $not_new_revision) {
            throw new Entity_Storage_Exception("An existing default revision of the '{$this->entity_type_id}' entity type can not be changed to a non-default revision.");
        }
        if (!$entity->is_new()) {
            // If the ID changed then original can't be loaded, throw an exception
            // in that case.
            if (!$entity->get_original() || $entity->id() != $entity->get_original()->id()) {
                throw new Entity_Storage_Exception("Update existing '{$this->entity_type_id}' entity while changing the ID is not supported.");
            }
            // Do not allow changing the revision ID when resaving the current
            // revision.
            if (!$entity->is_new_revision() && $entity->get_revision_id() != $entity->get_loaded_revision_id()) {
                throw new Entity_Storage_Exception("Update existing '{$this->entity_type_id}' entity revision while changing the revision ID is not supported.");
            }
        }
        return $id;
    }
    /**
     * {@inheritdoc}
     */
    protected function do_post_save(Entity_Interface $entity, $update)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        if ($update && $this->entity_type->is_translatable()) {
            $this->invoke_translation_hooks($entity);
        }
        parent::do_post_save($entity, $update);
        // The revision is stored, it should no longer be marked as new now.
        if ($this->entity_type->is_revisionable()) {
            $entity->update_loaded_revision_id();
            $entity->set_new_revision(false);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function do_delete($entities)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
        foreach ($entities as $entity) {
            $this->invoke_field_method('delete', $entity);
        }
        $this->do_delete_field_items($entities);
    }
    /**
     * Deletes entity field values from the storage.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
     *   An array of entity objects to be deleted.
     */
    abstract protected function do_delete_field_items($entities);
    /**
     * {@inheritdoc}
     */
    public function delete_revision($revision_id): void
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
        if ($revision = $this->load_revision($revision_id)) {
            // Prevent deletion if this is the default revision.
            if ($revision->is_default_revision()) {
                throw new Entity_Storage_Exception('Default revision can not be deleted');
            }
            $this->invoke_field_method('deleteRevision', $revision);
            $this->do_delete_revision_field_items($revision);
            $this->reset_revision_cache([$revision_id]);
            $this->invoke_hook('revision_delete', $revision);
        }
    }
    /**
     * Deletes field values of an entity revision from the storage.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $revision
     *   An entity revision object to be deleted.
     */
    abstract protected function do_delete_revision_field_items(Content_Entity_Interface $revision);
    /**
     * Checks translation statuses and invokes the related hooks if needed.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity being saved.
     */
    protected function invoke_translation_hooks(Content_Entity_Interface $entity)
    {
        $translations = $entity->get_translation_languages(false);
        $original_translations = $entity->get_original()->get_translation_languages(false);
        $all_translations = array_keys($translations + $original_translations);
        // Notify modules of translation insertion/deletion.
        foreach ($all_translations as $langcode) {
            if (isset($translations[$langcode]) && !isset($original_translations[$langcode])) {
                $this->invoke_hook('translation_insert', $entity->get_translation($langcode));
            } elseif (!isset($translations[$langcode]) && isset($original_translations[$langcode])) {
                $this->invoke_hook('translation_delete', $entity->get_original()->get_translation($langcode));
            }
        }
    }
    /**
     * Invokes hook_entity_storage_load().
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
     *   List of entities, keyed on the entity ID.
     */
    protected function invoke_storage_load_hook(array &$entities)
    {
        if (!empty($entities)) {
            // Call hook_entity_storage_load().
            $this->module_handler()->invoke_all_with('entity_storage_load', function (callable $hook, string $module) use (&$entities): void {
                $hook($entities, $this->entity_type_id);
            });
            // Call hook_TYPE_storage_load().
            $this->module_handler()->invoke_all_with($this->entity_type_id . '_storage_load', function (callable $hook, string $module) use (&$entities): void {
                $hook($entities);
            });
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function invoke_hook($hook, Entity_Interface $entity)
    {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        switch ($hook) {
            case 'presave':
                $this->invoke_field_method('preSave', $entity);
                break;
            case 'insert':
                $this->invoke_field_post_save($entity, false);
                break;
            case 'update':
                $this->invoke_field_post_save($entity, true);
                break;
        }
        parent::invoke_hook($hook, $entity);
    }
    /**
     * Invokes a method on the Field objects within an entity.
     *
     * Any argument passed will be forwarded to the invoked method.
     *
     * @param string $method
     *   The name of the method to be invoked.
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity object.
     *
     * @return array
     *   A multidimensional associative array of results, keyed by entity
     *   translation language code and field name.
     */
    protected function invoke_field_method($method, Content_Entity_Interface $entity)
    {
        $result = [];
        $args = array_slice(func_get_args(), 2);
        $langcodes = array_keys($entity->get_translation_languages());
        // Ensure that the field method is invoked as first on the current entity
        // translation and then on all other translations.
        $current_entity_langcode = $entity->language()->get_id();
        if (reset($langcodes) != $current_entity_langcode) {
            $langcodes = array_diff($langcodes, [$current_entity_langcode]);
            array_unshift($langcodes, $current_entity_langcode);
        }
        foreach ($langcodes as $langcode) {
            $translation = $entity->get_translation($langcode);
            // For non translatable fields, there is only one field object instance
            // across all translations and it has as parent entity the entity in the
            // default entity translation. Therefore field methods on non translatable
            // fields should be invoked only on the default entity translation.
            $fields = $translation->is_default_translation() ? $translation->get_fields() : $translation->get_translatable_fields();
            foreach ($fields as $name => $items) {
                // call_user_func_array() is way slower than a direct call so we avoid
                // using it if have no parameters.
                $result[$langcode][$name] = $args ? call_user_func_array([$items, $method], $args) : $items->{$method}();
            }
        }
        // We need to call the delete method for field items of removed
        // translations.
        if ($method == 'postSave' && $entity->get_original()) {
            $original_langcodes = array_keys($entity->get_original()->get_translation_languages());
            foreach (array_diff($original_langcodes, $langcodes) as $removed_langcode) {
                /** @var \Drupal\Core\Entity\ContentEntityInterface $translation */
                $translation = $entity->get_original()->get_translation($removed_langcode);
                // Fields may rely on the isDefaultTranslation() method to determine
                // what is going to be deleted - the whole entity or a particular
                // translation.
                if ($translation->is_default_translation()) {
                    $translation->set_default_translation_enforced(false);
                }
                $fields = $translation->get_translatable_fields();
                foreach ($fields as $items) {
                    $items->delete();
                }
            }
        }
        return $result;
    }
    /**
     * Invokes the post save method on the Field objects within an entity.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   The entity object.
     * @param bool $update
     *   Specifies whether the entity is being updated or created.
     */
    protected function invoke_field_post_save(Content_Entity_Interface $entity, $update)
    {
        // For each entity translation this returns an array of resave flags keyed
        // by field name, thus we merge them to obtain a list of fields to resave.
        $resave = [];
        foreach ($this->invoke_field_method('postSave', $entity, $update) as $translation_results) {
            $resave += array_filter($translation_results);
        }
        if ($resave) {
            $this->do_save_field_items($entity, array_keys($resave));
        }
    }
    /**
     * Checks whether the field values changed compared to the original entity.
     *
     * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
     *   Field definition of field to compare for changes.
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   Entity to check for field changes.
     * @param \Drupal\Core\Entity\ContentEntityInterface $original
     *   Original entity to compare against.
     *
     * @return bool
     *   True if the field value changed from the original entity.
     */
    protected function has_field_value_changed(Field_Definition_Interface $field_definition, Content_Entity_Interface $entity, Content_Entity_Interface $original)
    {
        $field_name = $field_definition->get_name();
        $langcodes = array_keys($entity->get_translation_languages());
        if ($langcodes !== array_keys($original->get_translation_languages())) {
            // If the list of langcodes has changed, we need to save.
            return true;
        }
        foreach ($langcodes as $langcode) {
            $items = $entity->get_translation($langcode)->get($field_name)->filter_empty_items();
            $original_items = $original->get_translation($langcode)->get($field_name)->filter_empty_items();
            // If the field items are not equal, we need to save.
            if (!$items->equals($original_items)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Populates the affected flag for all the revision translations.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface $entity
     *   An entity object being saved.
     */
    protected function populate_affected_revision_translations(Content_Entity_Interface $entity)
    {
        if ($this->entity_type->is_translatable() && $this->entity_type->is_revisionable()) {
            $languages = $entity->get_translation_languages();
            foreach ($languages as $langcode => $language) {
                $translation = $entity->get_translation($langcode);
                $current_affected = $translation->is_revision_translation_affected();
                if (!isset($current_affected) || $entity->is_new_revision() && !$translation->is_revision_translation_affected_enforced()) {
                    // When setting the revision translation affected flag we have to
                    // explicitly set it to not be enforced. By default it will be
                    // enforced automatically when being set, which allows us to determine
                    // if the flag has been already set outside the storage in which case
                    // we should not recompute it.
                    // @see \Drupal\Core\Entity\ContentEntityBase::setRevisionTranslationAffected().
                    $new_affected = $translation->has_translation_changes() ? true : null;
                    $translation->set_revision_translation_affected($new_affected);
                    $translation->set_revision_translation_affected_enforced(false);
                }
            }
        }
    }
    /**
     * Ensures integer entity key values are valid.
     *
     * The identifier sanitization provided by this method has been introduced
     * as Drupal used to rely on the database to facilitate this, which worked
     * correctly with MySQL but led to errors with other DBMS such as PostgreSQL.
     *
     * @param array $ids
     *   The entity key values to verify.
     * @param string $entity_key
     *   (optional) The entity key to sanitize values for. Defaults to 'id'.
     *
     * @return array
     *   The sanitized list of entity key values.
     */
    protected function clean_ids(array $ids, $entity_key = 'id')
    {
        if ($entity_key === 'revision' || $this->entity_type->has_integer_id()) {
            $ids = array_filter($ids, fn($id) => is_numeric($id) && $id == (int) $id);
            $ids = array_map(intval(...), $ids);
        }
        return $ids;
    }
    /**
     * Gets entities from the persistent cache backend.
     *
     * @param array|null &$ids
     *   If not empty, return entities that match these IDs. IDs that were found
     *   will be removed from the list.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface[]
     *   Array of entities from the persistent cache.
     */
    protected function get_from_persistent_cache(?array &$ids = null)
    {
        if (!$this->entity_type->is_persistently_cacheable() || empty($ids)) {
            return [];
        }
        $entities = [];
        // Build the list of cache entries to retrieve.
        $cid_map = [];
        foreach ($ids as $id) {
            $cid_map[$id] = $this->build_cache_id($id);
        }
        $cids = array_values($cid_map);
        if ($cache = $this->cache_backend->get_multiple($cids)) {
            // Get the entities that were found in the cache.
            foreach ($ids as $index => $id) {
                $cid = $cid_map[$id];
                if (isset($cache[$cid])) {
                    $entities[$id] = $cache[$cid]->data;
                    unset($ids[$index]);
                }
            }
        }
        return $entities;
    }
    /**
     * Gets entity revisions from the persistent cache backend.
     *
     * @param int[] &$ids
     *   Revision IDs to load from the revision cache. IDs that were found will be
     *   removed from the list.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface[]
     *   Array of entities from the persistent cache.
     */
    protected function get_from_persistent_revision_cache(array &$ids): array
    {
        if (!$this->entity_type->is_persistently_cacheable() || empty($ids)) {
            return [];
        }
        $entities = [];
        // Build the list of cache entries to retrieve.
        $cid_map = [];
        foreach ($ids as $id) {
            $cid_map[$id] = $this->build_revision_cache_id($id);
        }
        $cids = array_values($cid_map);
        if ($cache = $this->cache_backend->get_multiple($cids)) {
            // Get the entities that were found in the cache.
            foreach ($ids as $index => $id) {
                $cid = $cid_map[$id];
                if (isset($cache[$cid])) {
                    $entities[$id] = $cache[$cid]->data;
                    unset($ids[$index]);
                }
            }
        }
        return $entities;
    }
    /**
     * Stores entities in the persistent cache backend.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
     *   Entities to store in the cache.
     */
    protected function set_persistent_cache($entities)
    {
        if (!$this->entity_type->is_persistently_cacheable()) {
            return;
        }
        $items = [];
        foreach ($entities as $id => $entity) {
            $items[$this->build_cache_id($id)] = ['data' => $entity, 'tags' => ['entity_field_info']];
        }
        $this->cache_backend->set_multiple($items);
    }
    /**
     * Stores revisions in the persistent cache backend.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
     *   Entities to store in the cache.
     */
    protected function set_persistent_revision_cache(array $entities): void
    {
        if (!$this->entity_type->is_persistently_cacheable()) {
            return;
        }
        $items = [];
        $cache_tags = ['entity_field_info'];
        foreach ($entities as $entity) {
            // When an entity is cleared from the entity cache via ::resetCache()
            // we must clear the related revisions from the revision cache, as well.
            // To make this possible, we add a tag with the entity's ID to the
            // revision's cache entry.
            // @see \Drupal\Core\Entity\ContentEntityStorageBase::resetCache()
            $cache_tags[] = "{$this->entity_type_id}:{$entity->id()}:revisions";
            $items[$this->build_revision_cache_id($entity->get_revision_id())] = ['data' => $entity, 'tags' => $cache_tags];
        }
        $this->cache_backend->set_multiple($items);
    }
    /**
     * Builds the cache ID for the passed in revision ID.
     *
     * @param int $id
     *   Entity ID or revision ID for which the cache ID should be built.
     *
     * @return string
     *   Cache ID that can be passed to the cache backend.
     */
    protected function build_revision_cache_id($id): string
    {
        return "values:{$this->entity_type_id}:revision:{$id}";
    }
    /**
     * Gets entity revisions from the static cache.
     *
     * @param int[] $revision_ids
     *   Revision IDs to return from the static revision cache.
     *
     * @return \Drupal\Core\Entity\ContentEntityInterface[]
     *   An array of revisions from the cache.
     */
    protected function get_from_static_revision_cache(array $revision_ids): array
    {
        $revisions = [];
        // Load any available entities from the internal revision cache.
        if ($this->entity_type->is_statically_cacheable()) {
            $cache_ids = array_map(fn(int $revision_id) => $this->build_revision_cache_id($revision_id), $revision_ids);
            $map = array_combine($cache_ids, $revision_ids);
            $cache_items = $this->memory_cache->get_multiple($cache_ids);
            foreach ($cache_items as $cache_id => $item) {
                $revisions[$map[$cache_id]] = $item->data;
            }
        }
        return $revisions;
    }
    /**
     * Stores entities in the static entity and entity revision cache.
     *
     * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
     *   Entities to store in the cache.
     */
    protected function set_static_cache(array $entities)
    {
        parent::set_static_cache($entities);
        // Also make entities available in the static cache with their default
        // revision as they are frequently accessed through their revision ID, for
        // example when upcasting to the latest revision.
        if ($this->entity_type->is_statically_cacheable() && $this->entity_type->is_revisionable()) {
            foreach ($entities as $entity) {
                // @see \Drupal\Core\Entity\ContentEntityStorageBase::setPersistentRevisionCache()
                $cache_tags_revision = [$this->memory_cache_tag, "{$this->entity_type_id}:{$entity->id()}:revisions"];
                $this->memory_cache->set($this->build_revision_cache_id($entity->get_revision_id()), $entity, Memory_Cache_Interface::CACHE_PERMANENT, $cache_tags_revision);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function load_unchanged($id)
    {
        $entities = [];
        $ids = [$id];
        // The cache invalidation in the parent has the side effect that loading the
        // same entity again during the save process (for example in
        // hook_entity_presave()) will load the unchanged entity. Simulate this
        // by explicitly removing the entity from the static cache.
        parent::reset_cache($ids);
        // Gather entities from a 'preload' hook. This hook can be used by modules
        // that need, for example, to return a different revision than the default
        // one for revisionable entity types.
        $preloaded_entities = $this->pre_load($ids);
        if (!empty($preloaded_entities)) {
            $entities += $preloaded_entities;
        }
        // The default implementation in the parent class unsets the current cache
        // and then reloads the entity. That is slow, especially if this is done
        // repeatedly in the same request, e.g. when validating and then saving
        // an entity. Optimize this for content entities by trying to load them
        // directly from the persistent cache again, as in contrast to the static
        // cache the persistent one will never be changed until the entity is saved.
        $entities += $this->get_from_persistent_cache($ids);
        if (!$entities) {
            $entities[$id] = $this->load($id);
        } else {
            // As the entities are put into the persistent cache before the post load
            // has been executed we have to execute it if we have retrieved the
            // entity directly from the persistent cache.
            $this->post_load($entities);
            // As we've removed the entity from the static cache already we have to
            // put the loaded unchanged entity there to simulate the behavior of the
            // parent.
            $this->set_static_cache($entities);
        }
        return $entities[$id];
    }
    /**
     * Resets the entity cache.
     *
     * Content entities have both an in-memory static cache and a persistent
     * cache. Use this method to clear all caches. To clear just the in-memory
     * cache, use the 'entity.memory_cache' service.
     *
     * @param array $ids
     *   (optional) If specified, the cache is reset for the entities with the
     *   given ids only.
     */
    public function reset_cache(?array $ids = null): void
    {
        if ($ids) {
            parent::reset_cache($ids);
            $revisionable = $this->entity_type->is_revisionable();
            $cids = $latest_revision_cids = [];
            $revision_cache_tags = [];
            foreach ($ids as $id) {
                $cids[] = $this->build_cache_id($id);
                $latest_revision_cids[] = "latest_revision_id:{$this->entity_type_id}:{$id}";
                // Invalidate related entity revisions in the persistent entity cache.
                if ($revisionable) {
                    // Invalidate all revisions of this entity.
                    $revision_cache_tags[] = "{$this->entity_type_id}:{$id}:revisions";
                }
            }
            $this->memory_cache->delete_multiple($latest_revision_cids);
            if ($this->entity_type->is_persistently_cacheable()) {
                $this->cache_backend->delete_multiple($cids);
                if ($revision_cache_tags) {
                    // Invalidate related entity revisions in the persistent entity cache.
                    Cache::invalidate_tags($revision_cache_tags);
                }
            }
            if ($this->entity_type->is_statically_cacheable() && $revisionable && $revision_cache_tags) {
                // Invalidate related entity revisions in the memory entity cache.
                $this->memory_cache->invalidate_tags($revision_cache_tags);
            }
        } else {
            parent::reset_cache();
            if ($this->entity_type->is_persistently_cacheable()) {
                $this->cache_backend->delete_all();
            }
        }
    }
    /**
     * Resets the static and persistent revision caches.
     *
     * @param int[] $revision_ids
     *   The entity revision IDs to reset the static and persistent revision
     *   caches for.
     */
    protected function reset_revision_cache(array $revision_ids): void
    {
        $cache_ids = array_map(fn(int $revision_id) => $this->build_revision_cache_id($revision_id), $revision_ids);
        if ($this->entity_type->is_statically_cacheable()) {
            $this->memory_cache->delete_multiple($cache_ids);
        }
        if ($this->entity_type->is_persistently_cacheable()) {
            $this->cache_backend->delete_multiple($cache_ids);
        }
    }
}