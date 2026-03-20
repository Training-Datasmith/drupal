<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Component\Render\Formattable_Markup;
use Drupal\Core\Entity\Plugin\Data_Type\Entity_Reference;
use Drupal\Core\Field\Base_Field_Definition;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\Language_Interface;
use Drupal\Core\Session\Account_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
use Drupal\Core\Typed_Data\Translation_Status_Interface;
use Drupal\Core\Typed_Data\Typed_Data_Interface;
/**
 * Implements Entity Field API specific enhancements to the Entity class.
 *
 * @implements \IteratorAggregate<string, \Drupal\Core\Field\FieldItemListInterface>
 *
 * @ingroup entity_api
 */
abstract class Content_Entity_Base extends Entity_Base implements \IteratorAggregate, Content_Entity_Interface, Translation_Status_Interface
{
    use Entity_Changes_Detection_Trait {
        getFieldsToSkipFromTranslationChangesCheck as traitGetFieldsToSkipFromTranslationChangesCheck;
    }
    use Synchronizable_Entity_Trait;
    /**
     * The plain data values of the contained fields.
     *
     * This always holds the original, unchanged values of the entity. The values
     * are keyed by language code, whereas LanguageInterface::LANGCODE_DEFAULT
     * is used for values in default language.
     *
     *
     * @todo Add methods for getting original fields and for determining
     * changes.
     * @todo Provide a better way for defining default values.
     */
    protected array $values;
    /**
     * The array of fields, each being an instance of FieldItemListInterface.
     *
     * @var array
     */
    protected $fields = [];
    /**
     * Local cache for field definitions.
     *
     * @var array
     *
     * @see ContentEntityBase::getFieldDefinitions()
     */
    protected $field_definitions;
    /**
     * Local cache for the available language objects.
     *
     * @var \Drupal\Core\Language\LanguageInterface[]
     */
    protected $languages;
    /**
     * The language entity key.
     *
     * @var string
     */
    protected $langcode_key;
    /**
     * The default langcode entity key.
     *
     * @var string
     */
    protected $default_langcode_key;
    /**
     * Language code identifying the entity active language.
     *
     * This is the language field accessors will use to determine which field
     * values to manipulate.
     *
     * @var string
     */
    protected $active_langcode = Language_Interface::LANGCODE_DEFAULT;
    /**
     * Override the result of isDefaultTranslation().
     *
     * Under certain circumstances, such as when changing default translation, the
     * default value needs to be overridden.
     *
     *
     * @internal
     */
    protected ?bool $enforce_default_translation = null;
    /**
     * Local cache for the default language code.
     *
     * @var string
     */
    protected $default_langcode;
    /**
     * An array of entity translation metadata.
     *
     * An associative array keyed by translation language code. Every value is an
     * array containing the translation status and the translation object, if it
     * has already been instantiated.
     *
     * @var array
     */
    protected $translations = [];
    /**
     * A flag indicating whether a translation object is being initialized.
     *
     * @var bool
     */
    protected $translation_initialize = false;
    /**
     * Boolean indicating whether a new revision should be created on save.
     *
     * @var bool
     */
    protected $new_revision = false;
    /**
     * Indicates whether this is the default revision.
     *
     * @var bool
     */
    protected $is_default_revision = true;
    /**
     * Holds untranslatable entity keys such as the ID, bundle, and revision ID.
     *
     * @var array
     */
    protected $entity_keys = [];
    /**
     * Holds translatable entity keys such as the label.
     *
     * @var array
     */
    protected $translatable_entity_keys = [];
    /**
     * Whether entity validation was performed.
     *
     * @var bool
     */
    protected $validated = false;
    /**
     * Whether entity validation is required before saving the entity.
     *
     * @var bool
     */
    protected $validation_required = false;
    /**
     * The loaded revision ID before the new revision was set.
     *
     * @var int
     */
    protected $loaded_revision_id;
    /**
     * The revision translation affected entity key.
     *
     * @var string
     */
    protected $revision_translation_affected_key;
    /**
     * Whether the revision translation affected flag has been enforced.
     *
     * An array, keyed by the translation language code.
     *
     * @var bool[]
     */
    protected $enforce_revision_translation_affected = [];
    /**
     * Local cache for fields to skip from the checking for translation changes.
     *
     * @var array
     */
    protected static $fields_to_skip_from_translation_changes_check = [];
    /**
     * {@inheritdoc}
     */
    public function __construct(array $values, $entity_type, $bundle = false, $translations = [])
    {
        $this->entity_type_id = $entity_type;
        $this->entity_keys['bundle'] = $bundle ?: $this->entity_type_id;
        $this->langcode_key = $this->get_entity_type()->get_key('langcode');
        $this->default_langcode_key = $this->get_entity_type()->get_key('default_langcode');
        $this->revision_translation_affected_key = $this->get_entity_type()->get_key('revision_translation_affected');
        foreach ($values as $key => $value) {
            // If the key matches an existing property set the value to the property
            // to set properties like isDefaultRevision.
            // @todo Should this be converted somehow?
            if (property_exists($this, $key) && isset($value[Language_Interface::LANGCODE_DEFAULT])) {
                $this->{$key} = $value[Language_Interface::LANGCODE_DEFAULT];
            }
        }
        $this->values = $values;
        foreach ($this->get_entity_type()->get_keys() as $key => $field_name) {
            if (isset($this->values[$field_name])) {
                if (is_array($this->values[$field_name])) {
                    // We store untranslatable fields into an entity key without using a
                    // langcode key.
                    if (!$this->get_field_definition($field_name)->is_translatable()) {
                        if (isset($this->values[$field_name][Language_Interface::LANGCODE_DEFAULT])) {
                            if (is_array($this->values[$field_name][Language_Interface::LANGCODE_DEFAULT])) {
                                if (isset($this->values[$field_name][Language_Interface::LANGCODE_DEFAULT][0]['value'])) {
                                    $this->entity_keys[$key] = $this->values[$field_name][Language_Interface::LANGCODE_DEFAULT][0]['value'];
                                }
                            } else {
                                $this->entity_keys[$key] = $this->values[$field_name][Language_Interface::LANGCODE_DEFAULT];
                            }
                        }
                    } else {
                        // We save translatable fields such as the publishing status of a
                        // node into an entity key array keyed by langcode as a performance
                        // optimization, so we don't have to go through TypedData when we
                        // need these values.
                        foreach ($this->values[$field_name] as $langcode => $field_value) {
                            if (is_array($this->values[$field_name][$langcode])) {
                                if (isset($this->values[$field_name][$langcode][0]['value'])) {
                                    $this->translatable_entity_keys[$key][$langcode] = $this->values[$field_name][$langcode][0]['value'];
                                }
                            } else {
                                $this->translatable_entity_keys[$key][$langcode] = $this->values[$field_name][$langcode];
                            }
                        }
                    }
                }
            }
        }
        // Initialize translations. Ensure we have at least an entry for the default
        // language.
        // We determine if the entity is new by checking in the entity values for
        // the presence of the id entity key, as the usage of ::isNew() is not
        // possible in the constructor.
        $data = isset($values[$this->get_entity_type()->get_key('id')]) ? ['status' => static::TRANSLATION_EXISTING] : ['status' => static::TRANSLATION_CREATED];
        $this->translations[Language_Interface::LANGCODE_DEFAULT] = $data;
        $this->set_default_langcode();
        if ($translations) {
            foreach ($translations as $langcode) {
                if ($langcode != $this->default_langcode && $langcode != Language_Interface::LANGCODE_DEFAULT) {
                    $this->translations[$langcode] = $data;
                }
            }
        }
        if ($this->get_entity_type()->is_revisionable()) {
            // Store the loaded revision ID the entity has been loaded with to
            // keep it safe from changes.
            $this->update_loaded_revision_id();
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function get_languages()
    {
        if (empty($this->languages)) {
            $this->languages = $this->language_manager()->get_languages(Language_Interface::STATE_ALL);
            // If the entity references a language that is not or no longer available,
            // we return a mock language object to avoid disrupting the consuming
            // code.
            if (!isset($this->languages[$this->default_langcode])) {
                $this->languages[$this->default_langcode] = new Language(['id' => $this->default_langcode]);
            }
        }
        return $this->languages;
    }
    /**
     * {@inheritdoc}
     */
    public function post_create(Entity_Storage_Interface $storage): void
    {
        $this->new_revision = true;
    }
    /**
     * {@inheritdoc}
     */
    public function set_new_revision($value = true): void
    {
        if (!$this->get_entity_type()->has_key('revision')) {
            throw new \LogicException("Entity type {$this->get_entity_type_id()} does not support revisions.");
        }
        if ($value && !$this->new_revision) {
            // When saving a new revision, set any existing revision ID to NULL so as
            // to ensure that a new revision will actually be created.
            $this->set($this->get_entity_type()->get_key('revision'), null);
        } elseif (!$value && $this->new_revision) {
            // If ::setNewRevision(FALSE) is called after ::setNewRevision(TRUE) we
            // have to restore the loaded revision ID.
            $this->set($this->get_entity_type()->get_key('revision'), $this->get_loaded_revision_id());
        }
        $this->new_revision = $value;
    }
    /**
     * {@inheritdoc}
     */
    public function get_loaded_revision_id()
    {
        return $this->loaded_revision_id;
    }
    /**
     * {@inheritdoc}
     */
    public function update_loaded_revision_id()
    {
        $this->loaded_revision_id = $this->get_revision_id() ?: $this->loaded_revision_id;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_new_revision()
    {
        return $this->new_revision || $this->get_entity_type()->has_key('revision') && !$this->get_revision_id();
    }
    /**
     * {@inheritdoc}
     */
    public function is_default_revision($new_value = null)
    {
        $current_value = $this->is_default_revision;
        // New entities should always ensure at least one default revision exists,
        // creating an entity without a default revision is an invalid state.
        if (isset($new_value) && (!$this->is_new() || $new_value === true)) {
            $this->is_default_revision = (bool) $new_value;
        }
        return (bool) $current_value;
    }
    /**
     * {@inheritdoc}
     */
    public function was_default_revision()
    {
        /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
        $entity_type = $this->get_entity_type();
        if (!$entity_type->is_revisionable()) {
            return true;
        }
        $revision_default_key = $entity_type->get_revision_metadata_key('revision_default');
        if ($this->is_new()) {
            return true;
        }
        return (bool) $this->get($revision_default_key)->value;
    }
    /**
     * {@inheritdoc}
     */
    public function is_latest_revision()
    {
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = $this->entity_type_manager()->get_storage($this->get_entity_type_id());
        return $this->get_loaded_revision_id() == $storage->get_latest_revision_id($this->id());
    }
    /**
     * {@inheritdoc}
     */
    public function is_latest_translation_affected_revision()
    {
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = $this->entity_type_manager()->get_storage($this->get_entity_type_id());
        return $this->get_loaded_revision_id() == $storage->get_latest_translation_affected_revision_id($this->id(), $this->language()->get_id());
    }
    /**
     * {@inheritdoc}
     */
    public function is_revision_translation_affected()
    {
        return $this->has_field($this->revision_translation_affected_key) ? $this->get($this->revision_translation_affected_key)->value : true;
    }
    /**
     * {@inheritdoc}
     */
    public function set_revision_translation_affected($affected)
    {
        if ($this->has_field($this->revision_translation_affected_key)) {
            $this->set($this->revision_translation_affected_key, $affected);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_revision_translation_affected_enforced()
    {
        return !empty($this->enforce_revision_translation_affected[$this->active_langcode]);
    }
    /**
     * {@inheritdoc}
     */
    public function set_revision_translation_affected_enforced($enforced)
    {
        $this->enforce_revision_translation_affected[$this->active_langcode] = $enforced;
        return $this;
    }
    /**
     * Set or clear an override of the isDefaultTranslation() result.
     *
     * @param bool|null $enforce_default_translation
     *   If boolean value is passed, the value will override the result of
     *   isDefaultTranslation() method. If NULL is passed, the default logic will
     *   be used.
     *
     * @return $this
     */
    public function set_default_translation_enforced(?bool $enforce_default_translation): static
    {
        $this->enforce_default_translation = $enforce_default_translation;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function is_default_translation()
    {
        if ($this->enforce_default_translation !== null) {
            return $this->enforce_default_translation;
        }
        return $this->active_langcode === Language_Interface::LANGCODE_DEFAULT;
    }
    /**
     * {@inheritdoc}
     */
    public function get_revision_id()
    {
        return $this->get_entity_key('revision');
    }
    /**
     * {@inheritdoc}
     */
    public function is_translatable()
    {
        // Check the bundle is translatable, the entity has a language defined, and
        // the site has more than one language.
        $bundles = $this->entity_type_bundle_info()->get_bundle_info($this->entity_type_id);
        return !empty($bundles[$this->bundle()]['translatable']) && !$this->get_untranslated()->language()->is_locked() && $this->language_manager()->is_multilingual();
    }
    /**
     * {@inheritdoc}
     */
    public function pre_save(Entity_Storage_Interface $storage): void
    {
        // An entity requiring validation should not be saved if it has not been
        // actually validated.
        if ($this->validation_required && !$this->validated) {
            throw new \LogicException('Entity validation is required, but was skipped.');
        }
        $this->validated = false;
        parent::pre_save($storage);
    }
    /**
     * {@inheritdoc}
     */
    public function pre_save_revision(Entity_Storage_Interface $storage, \stdClass $record)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function post_save(Entity_Storage_Interface $storage, $update = true): void
    {
        parent::post_save($storage, $update);
        // Update the status of all saved translations.
        $removed = [];
        foreach ($this->translations as $langcode => &$data) {
            if ($data['status'] == static::TRANSLATION_REMOVED) {
                $removed[$langcode] = true;
            } else {
                $data['status'] = static::TRANSLATION_EXISTING;
            }
        }
        $this->translations = array_diff_key($this->translations, $removed);
        // Reset the new revision flag.
        $this->new_revision = false;
        // Reset the enforcement of the revision translation affected flag.
        $this->enforce_revision_translation_affected = [];
    }
    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        $this->validated = true;
        $violations = $this->get_typed_data()->validate();
        return new Entity_Constraint_Violation_List($this, $violations);
    }
    /**
     * {@inheritdoc}
     */
    public function is_validation_required()
    {
        return (bool) $this->validation_required;
    }
    /**
     * {@inheritdoc}
     */
    public function set_validation_required($required)
    {
        $this->validation_required = $required;
        return $this;
    }
    /**
     * Clears entity translation object cache to remove stale references.
     */
    protected function clear_translation_cache()
    {
        foreach ($this->translations as &$translation) {
            unset($translation['entity']);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function __sleep(): array
    {
        // Get the values of instantiated field objects, only serialize the values.
        foreach ($this->fields as $name => $fields) {
            foreach ($fields as $langcode => $field) {
                $this->values[$name][$langcode] = $field->get_value();
            }
        }
        $this->fields = [];
        $this->field_definitions = null;
        $this->languages = null;
        $this->clear_translation_cache();
        return parent::__sleep();
    }
    /**
     * {@inheritdoc}
     */
    public function id()
    {
        return $this->get_entity_key('id');
    }
    /**
     * {@inheritdoc}
     */
    public function bundle()
    {
        return $this->get_entity_key('bundle');
    }
    /**
     * {@inheritdoc}
     */
    public function get_bundle_entity(): ?Entity_Interface
    {
        $entity_type = $this->get_entity_type();
        if (!$entity_type->has_key('bundle') || !$entity_type->get_bundle_entity_type()) {
            return null;
        }
        return $this->get($entity_type->get_key('bundle'))->entity;
    }
    /**
     * {@inheritdoc}
     */
    public function uuid()
    {
        return $this->get_entity_key('uuid');
    }
    /**
     * {@inheritdoc}
     */
    public function has_field($field_name)
    {
        return (bool) $this->get_field_definition($field_name);
    }
    /**
     * {@inheritdoc}
     */
    public function get($field_name)
    {
        if (!isset($this->fields[$field_name][$this->active_langcode])) {
            return $this->get_translated_field($field_name, $this->active_langcode);
        }
        return $this->fields[$field_name][$this->active_langcode];
    }
    /**
     * Gets a translated field.
     *
     * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
     *   The translated field.
     */
    protected function get_translated_field($name, $langcode)
    {
        if ($this->translations[$this->active_langcode]['status'] == static::TRANSLATION_REMOVED) {
            throw new \InvalidArgumentException("The entity object refers to a removed translation ({$this->active_langcode}) and cannot be manipulated.");
        }
        // Populate $this->fields to speed-up further look-ups and to keep track of
        // fields objects, possibly holding changes to field values.
        if (!isset($this->fields[$name][$langcode])) {
            $definition = $this->get_field_definition($name);
            if (!$definition) {
                throw new \InvalidArgumentException("Field {$name} is unknown.");
            }
            // Non-translatable fields are always stored with
            // LanguageInterface::LANGCODE_DEFAULT as key.
            $default = $langcode == Language_Interface::LANGCODE_DEFAULT;
            if (!$default && !$definition->is_translatable()) {
                if (!isset($this->fields[$name][Language_Interface::LANGCODE_DEFAULT])) {
                    $this->fields[$name][Language_Interface::LANGCODE_DEFAULT] = $this->get_translated_field($name, Language_Interface::LANGCODE_DEFAULT);
                }
                $this->fields[$name][$langcode] =& $this->fields[$name][Language_Interface::LANGCODE_DEFAULT];
            } else {
                $value = null;
                if (isset($this->values[$name][$langcode])) {
                    $value = $this->values[$name][$langcode];
                }
                $field = \Drupal::service('plugin.manager.field.field_type')->create_field_item_list($this->get_translation($langcode), $name, $value);
                if ($default) {
                    // $this->defaultLangcode might not be set if we are initializing the
                    // default language code cache, in which case there is no valid
                    // langcode to assign.
                    $field_langcode = $this->default_langcode ?? Language_Interface::LANGCODE_NOT_SPECIFIED;
                } else {
                    $field_langcode = $langcode;
                }
                $field->set_langcode($field_langcode);
                $this->fields[$name][$langcode] = $field;
            }
        }
        return $this->fields[$name][$langcode];
    }
    /**
     * {@inheritdoc}
     */
    public function set($name, $value, $notify = true)
    {
        // Assign the value on the child and overrule notify such that we get
        // notified to handle changes afterwards. We can ignore notify as there is
        // no parent to notify anyway.
        $this->get($name)->set_value($value, true);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_fields($include_computed = true)
    {
        $fields = [];
        foreach ($this->get_field_definitions() as $name => $definition) {
            if ($include_computed || !$definition->is_computed()) {
                $fields[$name] = $this->get($name);
            }
        }
        return $fields;
    }
    /**
     * {@inheritdoc}
     */
    public function get_translatable_fields($include_computed = true)
    {
        $fields = [];
        foreach ($this->get_field_definitions() as $name => $definition) {
            if (($include_computed || !$definition->is_computed()) && $definition->is_translatable()) {
                $fields[$name] = $this->get($name);
            }
        }
        return $fields;
    }
    /**
     * Retrieves the iterator for the object.
     *
     * @return \ArrayIterator<string, \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
     *   The iterator.
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->get_fields());
    }
    /**
     * {@inheritdoc}
     */
    public function get_field_definition($name)
    {
        if (!isset($this->field_definitions)) {
            $this->get_field_definitions();
        }
        if (isset($this->field_definitions[$name])) {
            return $this->field_definitions[$name];
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_field_definitions()
    {
        if (!isset($this->field_definitions)) {
            $this->field_definitions = \Drupal::service('entity_field.manager')->get_field_definitions($this->entity_type_id, $this->bundle());
        }
        return $this->field_definitions;
    }
    /**
     * {@inheritdoc}
     */
    public function to_array()
    {
        $values = [];
        foreach ($this->get_fields() as $name => $property) {
            $values[$name] = $property->get_value();
        }
        return $values;
    }
    /**
     * {@inheritdoc}
     */
    public function access($operation, ?Account_Interface $account = null, $return_as_object = false)
    {
        if ($operation == 'create') {
            return $this->entity_type_manager()->get_access_control_handler($this->entity_type_id)->create_access($this->bundle(), $account, [], $return_as_object);
        }
        return $this->entity_type_manager()->get_access_control_handler($this->entity_type_id)->access($this, $operation, $account, $return_as_object);
    }
    /**
     * {@inheritdoc}
     */
    public function language()
    {
        if ($this->active_langcode != Language_Interface::LANGCODE_DEFAULT) {
            if (!isset($this->languages[$this->active_langcode])) {
                $this->get_languages();
            }
            return $this->languages[$this->active_langcode];
        }
        // @todo Avoid this check by getting the language from the language
        //   manager directly in https://www.drupal.org/node/2303877.
        if (!isset($this->languages[$this->default_langcode])) {
            $this->get_languages();
        }
        return $this->languages[$this->default_langcode];
    }
    /**
     * Populates the local cache for the default language code.
     */
    protected function set_default_langcode()
    {
        // Get the language code if the property exists.
        // Try to read the value directly from the list of entity keys which got
        // initialized in __construct(). This avoids creating a field item object.
        if (isset($this->translatable_entity_keys['langcode'][$this->active_langcode])) {
            $this->default_langcode = $this->translatable_entity_keys['langcode'][$this->active_langcode];
        } elseif ($this->has_field($this->langcode_key) && ($item = $this->get($this->langcode_key)) && isset($item->language)) {
            $this->default_langcode = $item->language->get_id();
            $this->translatable_entity_keys['langcode'][$this->active_langcode] = $this->default_langcode;
        }
        if (empty($this->default_langcode)) {
            // Make sure we return a proper language object, if the entity has a
            // langcode field, default to the site's default language.
            if ($this->has_field($this->langcode_key)) {
                $this->default_langcode = $this->language_manager()->get_default_language()->get_id();
            } else {
                $this->default_langcode = Language_Interface::LANGCODE_NOT_SPECIFIED;
            }
        }
        // This needs to be initialized manually as it is skipped when instantiating
        // the language field object to avoid infinite recursion.
        if (!empty($this->fields[$this->langcode_key])) {
            $this->fields[$this->langcode_key][Language_Interface::LANGCODE_DEFAULT]->set_langcode($this->default_langcode);
        }
    }
    /**
     * Updates language for already instantiated fields.
     */
    protected function update_field_langcodes($langcode)
    {
        foreach ($this->fields as $items) {
            if (!empty($items[Language_Interface::LANGCODE_DEFAULT])) {
                $items[Language_Interface::LANGCODE_DEFAULT]->set_langcode($langcode);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function on_change($name): void
    {
        // Check if the changed name is the value of any entity keys and if any of
        // those values are currently cached, if so, reset it. Exclude the bundle
        // from that check, as it ready only and must not change, unsetting it could
        // lead to recursions.
        foreach (array_keys($this->get_entity_type()->get_keys(), $name, true) as $key) {
            if ($key != 'bundle') {
                if (isset($this->entity_keys[$key])) {
                    unset($this->entity_keys[$key]);
                } elseif (isset($this->translatable_entity_keys[$key][$this->active_langcode])) {
                    unset($this->translatable_entity_keys[$key][$this->active_langcode]);
                }
                // If the revision identifier field is being populated with the original
                // value, we need to make sure the "new revision" flag is reset
                // accordingly.
                if ($key === 'revision' && $this->get_revision_id() == $this->get_loaded_revision_id() && !$this->is_new()) {
                    $this->new_revision = false;
                }
            }
        }
        switch ($name) {
            case $this->langcode_key:
                if ($this->is_default_translation()) {
                    // Update the default internal language cache.
                    $this->set_default_langcode();
                    if (isset($this->translations[$this->default_langcode])) {
                        $message = new Formattable_Markup('A translation already exists for the specified language (@langcode).', ['@langcode' => $this->default_langcode]);
                        throw new \InvalidArgumentException($message);
                    }
                    $this->update_field_langcodes($this->default_langcode);
                } else {
                    // @todo Allow the translation language to be changed. See
                    //   https://www.drupal.org/node/2443989.
                    $items = $this->get($this->langcode_key);
                    if ($items->value != $this->active_langcode) {
                        $items->set_value($this->active_langcode, false);
                        $message = new Formattable_Markup('The translation language cannot be changed (@langcode).', ['@langcode' => $this->active_langcode]);
                        throw new \LogicException($message);
                    }
                }
                break;
            case $this->default_langcode_key:
                // @todo Use a standard method to make the default_langcode field
                //   read-only. See https://www.drupal.org/node/2443991.
                if (isset($this->values[$this->default_langcode_key]) && $this->get($this->default_langcode_key)->value != $this->is_default_translation()) {
                    $this->get($this->default_langcode_key)->set_value($this->is_default_translation(), false);
                    $message = new Formattable_Markup('The default translation flag cannot be changed (@langcode).', ['@langcode' => $this->active_langcode]);
                    throw new \LogicException($message);
                }
                break;
            case $this->revision_translation_affected_key:
                // If the revision translation affected flag is being set then enforce
                // its value.
                $this->set_revision_translation_affected_enforced(true);
                break;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_translation($langcode)
    {
        // Ensure we always use the default language code when dealing with the
        // original entity language.
        if ($langcode != Language_Interface::LANGCODE_DEFAULT && $langcode == $this->default_langcode) {
            $langcode = Language_Interface::LANGCODE_DEFAULT;
        }
        // Populate entity translation object cache so it will be available for all
        // translation objects.
        if (!isset($this->translations[$this->active_langcode]['entity'])) {
            $this->translations[$this->active_langcode]['entity'] = $this;
        }
        // If we already have a translation object for the specified language we can
        // just return it.
        if (isset($this->translations[$langcode]['entity'])) {
            $translation = $this->translations[$langcode]['entity'];
        } elseif (isset($this->translations[$langcode])) {
            $translation = $this->initialize_translation($langcode);
            $this->translations[$langcode]['entity'] = $translation;
        }
        if (empty($translation)) {
            throw new \InvalidArgumentException("Invalid translation language ({$langcode}) specified.");
        }
        return $translation;
    }
    /**
     * {@inheritdoc}
     */
    public function get_untranslated()
    {
        return $this->get_translation(Language_Interface::LANGCODE_DEFAULT);
    }
    /**
     * Instantiates a translation object for an existing translation.
     *
     * The translated entity will be a clone of the current entity with the
     * specified $langcode. All translations share the same field data structures
     * to ensure that all of them deal with fresh data.
     *
     * @param string $langcode
     *   The language code for the requested translation.
     *
     * @return \Drupal\Core\Entity\EntityInterface
     *   The translation object. The content properties of the translation object
     *   are stored as references to the main entity.
     */
    protected function initialize_translation($langcode)
    {
        // If the requested translation is valid, clone it with the current language
        // as the active language. The $translationInitialize flag triggers a
        // shallow (non-recursive) clone.
        $this->translation_initialize = true;
        $translation = clone $this;
        $this->translation_initialize = false;
        $translation->active_langcode = $langcode;
        // Ensure that changes to fields, values and translations are propagated
        // to all the translation objects.
        // @todo Consider converting these to ArrayObject.
        $translation->values =& $this->values;
        $translation->fields =& $this->fields;
        $translation->translations =& $this->translations;
        $translation->enforce_is_new =& $this->enforce_is_new;
        $translation->new_revision =& $this->new_revision;
        $translation->entity_keys =& $this->entity_keys;
        $translation->translatable_entity_keys =& $this->translatable_entity_keys;
        $translation->translation_initialize = false;
        $translation->typed_data = null;
        $translation->loaded_revision_id =& $this->loaded_revision_id;
        $translation->is_default_revision =& $this->is_default_revision;
        $translation->enforce_revision_translation_affected =& $this->enforce_revision_translation_affected;
        $translation->is_syncing =& $this->is_syncing;
        $translation->original_entity =& $this->original_entity;
        return $translation;
    }
    /**
     * {@inheritdoc}
     */
    public function has_translation($langcode)
    {
        if ($langcode == $this->default_langcode) {
            $langcode = Language_Interface::LANGCODE_DEFAULT;
        }
        return !empty($this->translations[$langcode]['status']);
    }
    /**
     * {@inheritdoc}
     */
    public function is_new_translation()
    {
        return $this->translations[$this->active_langcode]['status'] == static::TRANSLATION_CREATED;
    }
    /**
     * {@inheritdoc}
     */
    public function add_translation($langcode, array $values = [])
    {
        // Make sure we do not attempt to create a translation if an invalid
        // language is specified or the entity cannot be translated.
        $this->get_languages();
        if (!isset($this->languages[$langcode]) || $this->has_translation($langcode) || $this->languages[$langcode]->is_locked()) {
            throw new \InvalidArgumentException("Invalid translation language ({$langcode}) specified.");
        }
        if ($this->languages[$this->default_langcode]->is_locked()) {
            throw new \InvalidArgumentException("The entity cannot be translated since it is language neutral ({$this->default_langcode}).");
        }
        // Initialize the translation object.
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = $this->entity_type_manager()->get_storage($this->get_entity_type_id());
        $this->translations[$langcode]['status'] = !isset($this->translations[$langcode]['status_existed']) ? static::TRANSLATION_CREATED : static::TRANSLATION_EXISTING;
        return $storage->create_translation($this, $langcode, $values);
    }
    /**
     * {@inheritdoc}
     */
    public function remove_translation($langcode): void
    {
        if (isset($this->translations[$langcode]) && $langcode != Language_Interface::LANGCODE_DEFAULT && $langcode != $this->default_langcode) {
            foreach ($this->get_field_definitions() as $name => $definition) {
                if ($definition->is_translatable()) {
                    unset($this->values[$name][$langcode]);
                    unset($this->fields[$name][$langcode]);
                }
            }
            // If removing a translation which has not been saved yet, then we have
            // to remove it completely so that ::getTranslationStatus returns the
            // proper status.
            if ($this->translations[$langcode]['status'] == static::TRANSLATION_CREATED) {
                unset($this->translations[$langcode]);
            } else {
                if ($this->translations[$langcode]['status'] == static::TRANSLATION_EXISTING) {
                    $this->translations[$langcode]['status_existed'] = true;
                }
                $this->translations[$langcode]['status'] = static::TRANSLATION_REMOVED;
            }
        } else {
            throw new \InvalidArgumentException("The specified translation ({$langcode}) cannot be removed.");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_translation_status($langcode)
    {
        if ($langcode == $this->default_langcode) {
            $langcode = Language_Interface::LANGCODE_DEFAULT;
        }
        return isset($this->translations[$langcode]) ? $this->translations[$langcode]['status'] : null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_translation_languages($include_default = true)
    {
        $translations = array_filter($this->translations, fn(array $translation) => $translation['status']);
        unset($translations[Language_Interface::LANGCODE_DEFAULT]);
        if ($include_default) {
            $translations[$this->default_langcode] = true;
        }
        // Now load language objects based upon translation langcodes.
        return array_intersect_key($this->get_languages(), $translations);
    }
    /**
     * Updates the original values with the interim changes.
     */
    public function update_original_values(): void
    {
        if (!$this->fields) {
            return;
        }
        foreach ($this->get_field_definitions() as $name => $definition) {
            if (!$definition->is_computed() && !empty($this->fields[$name])) {
                foreach ($this->fields[$name] as $langcode => $item) {
                    $item->filter_empty_items();
                    $this->values[$name][$langcode] = $item->get_value();
                }
            }
        }
    }
    /**
     * Implements the magic method for getting object properties.
     *
     * @todo A lot of code still uses non-fields (e.g. $entity->content in view
     *   builders) by reference. Clean that up.
     */
    public function &__get(string $name): mixed
    {
        // If this is an entity field, handle it accordingly. We first check whether
        // a field object has been already created. If not, we create one.
        if (isset($this->fields[$name][$this->active_langcode])) {
            return $this->fields[$name][$this->active_langcode];
        }
        // Inline getFieldDefinition() to speed things up.
        if (!isset($this->field_definitions)) {
            $this->get_field_definitions();
        }
        if (isset($this->field_definitions[$name])) {
            $return = $this->get_translated_field($name, $this->active_langcode);
            return $return;
        }
        // Else directly read/write plain values. That way, non-field entity
        // properties can always be accessed directly.
        if (!isset($this->values[$name])) {
            $this->values[$name] = null;
        }
        return $this->values[$name];
    }
    /**
     * Implements the magic method for setting object properties.
     *
     * Uses default language always.
     */
    public function __set(string $name, mixed $value)
    {
        // Inline getFieldDefinition() to speed things up.
        if (!isset($this->field_definitions)) {
            $this->get_field_definitions();
        }
        // Handle Field API fields.
        if (isset($this->field_definitions[$name])) {
            // Support setting values via property objects.
            if ($value instanceof Typed_Data_Interface) {
                $value = $value->get_value();
            }
            // If a FieldItemList object already exists, set its value.
            if (isset($this->fields[$name][$this->active_langcode])) {
                $this->fields[$name][$this->active_langcode]->set_value($value);
            } else {
                $this->get_translated_field($name, $this->active_langcode)->set_value($value);
            }
        } elseif ($name == 'translations') {
            $this->translations = $value;
        } else {
            $this->values[$name] = $value;
        }
    }
    /**
     * Implements the magic method for isset().
     */
    public function __isset(string $name)
    {
        // "Official" Field API fields are always set. For non-field properties,
        // check the internal values.
        return $this->has_field($name) ? true : isset($this->values[$name]);
    }
    /**
     * Implements the magic method for unset().
     */
    public function __unset(string $name)
    {
        // Unsetting a field means emptying it.
        if ($this->has_field($name)) {
            $this->get($name)->set_value([]);
        } else {
            unset($this->values[$name]);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function create(array $values = [])
    {
        $entity_type_repository = \Drupal::service('entity_type.repository');
        $entity_type_manager = \Drupal::entity_type_manager();
        $class_name = static::class;
        $storage = $entity_type_manager->get_storage($entity_type_repository->get_entity_type_from_class($class_name));
        // Always explicitly specify the bundle if the entity has a bundle class.
        if ($storage instanceof Bundle_Entity_Storage_Interface && $bundle = $storage->get_bundle_from_class($class_name)) {
            $values[$storage->get_entity_type()->get_key('bundle')] = $bundle;
        }
        return $storage->create($values);
    }
    /**
     * {@inheritdoc}
     */
    public function create_duplicate()
    {
        if ($this->translations[$this->active_langcode]['status'] == static::TRANSLATION_REMOVED) {
            throw new \InvalidArgumentException("The entity object refers to a removed translation ({$this->active_langcode}) and cannot be manipulated.");
        }
        $duplicate = clone $this;
        $entity_type = $this->get_entity_type();
        if ($entity_type->has_key('id')) {
            $duplicate->{$entity_type->get_key('id')}->value = null;
        }
        // Explicitly mark the entity as new and the default revision. A new entity
        // is always the default revision, but that persists only until the entity
        // is saved.
        $duplicate->enforce_is_new();
        $duplicate->is_default_revision(true);
        // Check if the entity type supports UUIDs and generate a new one if so.
        if ($entity_type->has_key('uuid')) {
            $duplicate->{$entity_type->get_key('uuid')}->value = $this->uuid_generator()->generate();
        }
        // Check whether the entity type supports revisions and initialize it if so.
        if ($entity_type->is_revisionable()) {
            $duplicate->{$entity_type->get_key('revision')}->value = null;
            $duplicate->loaded_revision_id = null;
        }
        // Modules might need to add or change the data initially held by the new
        // entity object, for instance to fill-in default values.
        \Drupal::module_handler()->invoke_all($this->get_entity_type_id() . '_duplicate', [$duplicate, $this]);
        \Drupal::module_handler()->invoke_all('entity_duplicate', [$duplicate, $this]);
        return $duplicate;
    }
    /**
     * Magic method: Implements a deep clone.
     */
    public function __clone()
    {
        // Avoid deep-cloning when we are initializing a translation object, since
        // it will represent the same entity, only with a different active language.
        if ($this->translation_initialize) {
            return;
        }
        // The translation is a different object, and needs its own TypedData
        // adapter object.
        $this->typed_data = null;
        $definitions = $this->get_field_definitions();
        // The translation cache has to be cleared before cloning the fields
        // below so that the call to getTranslation() does not re-use the
        // translation objects of the old entity but instead creates new
        // translation objects from the newly cloned entity. Otherwise the newly
        // cloned field item lists would hold references to the old translation
        // objects in their $parent property after the call to setContext().
        $this->clear_translation_cache();
        // Because the new translation objects that are created below are
        // themselves created by *cloning* the newly cloned entity we need to
        // make sure that the references to property values are properly cloned
        // before cloning the fields. Otherwise calling
        // $items->getEntity()->isNew(), for example, would return the
        // $enforceIsNew value of the old entity.
        // Ensure the translations array is actually cloned by overwriting the
        // original reference with one pointing to a copy of the array.
        $translations = $this->translations;
        $this->translations =& $translations;
        // Ensure that the following properties are actually cloned by
        // overwriting the original references with ones pointing to copies of
        // them: enforceIsNew, newRevision, loadedRevisionId, fields, entityKeys,
        // translatableEntityKeys, values, isDefaultRevision and
        // enforceRevisionTranslationAffected.
        $enforce_is_new = $this->enforce_is_new;
        $this->enforce_is_new =& $enforce_is_new;
        $new_revision = $this->new_revision;
        $this->new_revision =& $new_revision;
        $original_revision_id = $this->loaded_revision_id;
        $this->loaded_revision_id =& $original_revision_id;
        $fields = $this->fields;
        $this->fields =& $fields;
        $entity_keys = $this->entity_keys;
        $this->entity_keys =& $entity_keys;
        $translatable_entity_keys = $this->translatable_entity_keys;
        $this->translatable_entity_keys =& $translatable_entity_keys;
        $values = $this->values;
        $this->values =& $values;
        $default_revision = $this->is_default_revision;
        $this->is_default_revision =& $default_revision;
        $is_revision_translation_affected_enforced = $this->enforce_revision_translation_affected;
        $this->enforce_revision_translation_affected =& $is_revision_translation_affected_enforced;
        $is_syncing = $this->is_syncing;
        $this->is_syncing =& $is_syncing;
        foreach ($this->fields as $name => $fields_by_langcode) {
            $this->fields[$name] = [];
            // Untranslatable fields may have multiple references for the same field
            // object keyed by language. To avoid creating different field objects
            // we retain just the original value, as references will be recreated
            // later as needed.
            if (!$definitions[$name]->is_translatable() && count($fields_by_langcode) > 1) {
                $fields_by_langcode = array_intersect_key($fields_by_langcode, [Language_Interface::LANGCODE_DEFAULT => true]);
            }
            foreach ($fields_by_langcode as $langcode => $items) {
                $this->fields[$name][$langcode] = clone $items;
                $this->fields[$name][$langcode]->set_context($name, $this->get_translation($langcode)->get_typed_data());
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function label()
    {
        if ($this->get_entity_type()->get_key('label')) {
            return $this->get_entity_key('label');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function referenced_entities()
    {
        $referenced_entities = [];
        // Gather a list of referenced entities.
        foreach ($this->get_fields() as $field_items) {
            foreach ($field_items as $field_item) {
                // Loop over all properties of a field item.
                foreach ($field_item->get_properties(true) as $property) {
                    if ($property instanceof Entity_Reference && $entity = $property->get_value()) {
                        $referenced_entities[] = $entity;
                    }
                }
            }
        }
        return $referenced_entities;
    }
    /**
     * Gets the value of the given entity key, if defined.
     *
     * @param string $key
     *   Name of the entity key, for example id, revision or bundle.
     *
     * @return mixed
     *   The value of the entity key, NULL if not defined.
     */
    protected function get_entity_key($key)
    {
        // If the value is known already, return it.
        if (isset($this->entity_keys[$key])) {
            return $this->entity_keys[$key];
        }
        if (isset($this->translatable_entity_keys[$key][$this->active_langcode])) {
            return $this->translatable_entity_keys[$key][$this->active_langcode];
        }
        // Otherwise fetch the value by creating a field object.
        $value = null;
        if ($this->get_entity_type()->has_key($key)) {
            $field_name = $this->get_entity_type()->get_key($key);
            $definition = $this->get_field_definition($field_name);
            $property = $definition->get_field_storage_definition()->get_main_property_name();
            $value = $this->get($field_name)->{$property};
            // Put it in the right array, depending on whether it is translatable.
            if ($definition->is_translatable()) {
                $this->translatable_entity_keys[$key][$this->active_langcode] = $value;
            } else {
                $this->entity_keys[$key] = $value;
            }
        } else {
            $this->entity_keys[$key] = $value;
        }
        return $value;
    }
    /**
     * {@inheritdoc}
     */
    public static function base_field_definitions(Entity_Type_Interface $entity_type)
    {
        $fields = [];
        if ($entity_type->has_key('id')) {
            $fields[$entity_type->get_key('id')] = Base_Field_Definition::create('integer')->set_label(new Translatable_Markup('ID'))->set_read_only(true)->set_setting('unsigned', true);
        }
        if ($entity_type->has_key('uuid')) {
            $fields[$entity_type->get_key('uuid')] = Base_Field_Definition::create('uuid')->set_label(new Translatable_Markup('UUID'))->set_read_only(true);
        }
        if ($entity_type->has_key('revision')) {
            $fields[$entity_type->get_key('revision')] = Base_Field_Definition::create('integer')->set_label(new Translatable_Markup('Revision ID'))->set_read_only(true)->set_setting('unsigned', true);
        }
        if ($entity_type->has_key('langcode')) {
            $fields[$entity_type->get_key('langcode')] = Base_Field_Definition::create('language')->set_label(new Translatable_Markup('Language'))->set_display_options('view', ['region' => 'hidden'])->set_display_options('form', ['type' => 'language_select', 'weight' => 2]);
            if ($entity_type->is_revisionable()) {
                $fields[$entity_type->get_key('langcode')]->set_revisionable(true);
            }
            if ($entity_type->is_translatable()) {
                $fields[$entity_type->get_key('langcode')]->set_translatable(true);
            }
        }
        if ($entity_type->has_key('bundle')) {
            if ($bundle_entity_type_id = $entity_type->get_bundle_entity_type()) {
                $fields[$entity_type->get_key('bundle')] = Base_Field_Definition::create('entity_reference')->set_label($entity_type->get_bundle_label())->set_setting('target_type', $bundle_entity_type_id)->set_required(true)->set_read_only(true);
            } else {
                $fields[$entity_type->get_key('bundle')] = Base_Field_Definition::create('string')->set_label($entity_type->get_bundle_label())->set_required(true)->set_read_only(true);
            }
        }
        return $fields;
    }
    /**
     * {@inheritdoc}
     */
    public static function bundle_field_definitions(Entity_Type_Interface $entity_type, $bundle, array $base_field_definitions)
    {
        return [];
    }
    /**
     * Returns an array of field names to skip in ::hasTranslationChanges.
     *
     * @return array
     *   An array of field names.
     */
    protected function get_fields_to_skip_from_translation_changes_check()
    {
        $bundle = $this->bundle();
        if (!isset(static::$fields_to_skip_from_translation_changes_check[$this->entity_type_id][$bundle])) {
            static::$fields_to_skip_from_translation_changes_check[$this->entity_type_id][$bundle] = $this->trait_get_fields_to_skip_from_translation_changes_check($this);
        }
        return static::$fields_to_skip_from_translation_changes_check[$this->entity_type_id][$bundle];
    }
    /**
     * {@inheritdoc}
     */
    public function has_translation_changes()
    {
        if ($this->is_new()) {
            return true;
        }
        // The original entity only exists during save. See
        // \Drupal\Core\Entity\EntityStorageBase::save(). If it exists we re-use it
        // here for performance reasons.
        /** @var \Drupal\Core\Entity\ContentEntityBase $original */
        $original = $this->get_original();
        if (!$original) {
            $id = $this->get_original_id() ?? $this->id();
            $storage = $this->entity_type_manager()->get_storage($this->get_entity_type_id());
            $original = $this->get_loaded_revision_id() && $storage instanceof Revisionable_Storage_Interface ? $storage->load_revision_unchanged($this->get_loaded_revision_id()) : $storage->load_unchanged($id);
        }
        // If the current translation has just been added, we have a change.
        $translated = count($this->translations) > 1;
        if ($translated && !$original->has_translation($this->active_langcode)) {
            return true;
        }
        // Compare field item current values with the original ones to determine
        // whether we have changes. If a field is not translatable and the entity is
        // translated we skip it because, depending on the use case, it would make
        // sense to mark all translations as changed or none of them. We skip also
        // computed fields as comparing them with their original values might not be
        // possible or be meaningless.
        /** @var \Drupal\Core\Entity\ContentEntityBase $translation */
        $translation = $original->get_translation($this->active_langcode);
        $langcode = $this->language()->get_id();
        // The list of fields to skip from the comparison.
        $skip_fields = $this->get_fields_to_skip_from_translation_changes_check();
        // We also check untranslatable fields, so that a change to those will mark
        // all translations as affected, unless they are configured to only affect
        // the default translation.
        $skip_untranslatable_fields = !$this->is_default_translation() && $this->is_default_translation_affected_only();
        foreach ($this->get_field_definitions() as $field_name => $definition) {
            // @todo Avoid special-casing the following fields. See
            //   https://www.drupal.org/node/2329253.
            if (in_array($field_name, $skip_fields, true)) {
                continue;
            }
            if ($skip_untranslatable_fields && !$definition->is_translatable()) {
                continue;
            }
            $items = $this->get($field_name)->filter_empty_items();
            $original_items = $translation->get($field_name)->filter_empty_items();
            if ($items->has_affecting_changes($original_items, $langcode)) {
                return true;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function is_default_translation_affected_only()
    {
        $bundle_name = $this->bundle();
        $bundle_info = \Drupal::service('entity_type.bundle.info')->get_bundle_info($this->get_entity_type_id());
        return !empty($bundle_info[$bundle_name]['untranslatable_fields.default_translation_affected']);
    }
}