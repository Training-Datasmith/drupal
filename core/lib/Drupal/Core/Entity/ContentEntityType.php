<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

/**
 * Provides an implementation of a content entity type and its metadata.
 */
class Content_Entity_Type extends Entity_Type implements Content_Entity_Type_Interface
{
    /**
     * An array of entity revision metadata keys.
     *
     * @var array
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $revision_metadata_keys = [];
    /**
     * {@inheritdoc}
     */
    public function __construct($definition)
    {
        parent::__construct($definition);
        $this->handlers += ['storage' => \Drupal\Core\Entity\Sql\Sql_Content_Entity_Storage::class, 'view_builder' => \Drupal\Core\Entity\Entity_View_Builder::class];
        $this->revision_metadata_keys += ['revision_default' => 'revision_default'];
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_dependency_key(): string
    {
        return 'content';
    }
    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     *   If the provided class does not implement
     *   \Drupal\Core\Entity\ContentEntityStorageInterface.
     *
     * @see \Drupal\Core\Entity\ContentEntityStorageInterface
     */
    protected function check_storage_class($class)
    {
        $required_interface = Content_Entity_Storage_Interface::class;
        if (!is_subclass_of($class, $required_interface)) {
            throw new \InvalidArgumentException("{$class} does not implement {$required_interface}");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_revision_metadata_keys()
    {
        return $this->revision_metadata_keys;
    }
    /**
     * {@inheritdoc}
     */
    public function get_revision_metadata_key($key)
    {
        $keys = $this->get_revision_metadata_keys();
        return $keys[$key] ?? false;
    }
    /**
     * {@inheritdoc}
     */
    public function has_revision_metadata_key($key): bool
    {
        $keys = $this->get_revision_metadata_keys();
        return isset($keys[$key]);
    }
    /**
     * {@inheritdoc}
     */
    public function set_revision_metadata_key($key, $field_name): static
    {
        if ($field_name !== null) {
            $this->revision_metadata_keys[$key] = $field_name;
        } else {
            unset($this->revision_metadata_keys[$key]);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function has_integer_id(): ?bool
    {
        if ($this->has_key('id') && $this->entity_class_implements(Fieldable_Entity_Interface::class)) {
            $definitions = \Drupal::service('entity_field.manager')->get_base_field_definitions($this->id());
            return $definitions[$this->get_key('id')]->get_type() === 'integer';
        }
        return null;
    }
}