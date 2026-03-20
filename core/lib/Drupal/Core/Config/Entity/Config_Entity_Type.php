<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Config\Config_Prefix_Length_Exception;
use Drupal\Core\Config\Entity\Exception\Config_Entity_Storage_Class_Exception;
use Drupal\Core\Entity\Entity_Type;
/**
 * Provides an implementation of a configuration entity type and its metadata.
 */
class Config_Entity_Type extends Entity_Type implements Config_Entity_Type_Interface
{
    /**
     * The config prefix set in the configuration entity type annotation.
     *
     * @var string
     *
     * @see \Drupal\Core\Config\Entity\ConfigEntityTypeInterface::getConfigPrefix()
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $config_prefix;
    /**
     * {@inheritdoc}
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $static_cache = false;
    /**
     * Keys that are stored key value store for fast lookup.
     *
     * @var array
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $lookup_keys = [];
    /**
     * The list of configuration entity properties to export from the annotation.
     *
     * @var array
     */
    // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
    protected $config_export = [];
    /**
     * The result of merging config_export annotation with the defaults.
     *
     * This is stored on the class so that it does not have to be recalculated.
     *
     * @var array
     */
    protected $merged_config_export = [];
    /**
     * {@inheritdoc}
     *
     * @throws \Drupal\Core\Config\Entity\Exception\ConfigEntityStorageClassException
     *   Exception thrown when the provided class is not an instance of
     *   \Drupal\Core\Config\Entity\ConfigEntityStorage.
     */
    public function __construct($definition)
    {
        // Ensure a default list cache tag is set; do this before calling the parent
        // constructor, because we want "Configuration System style" cache tags.
        if (empty($definition['list_cache_tags'])) {
            $definition['list_cache_tags'] = ['config:' . $definition['id'] . '_list'];
        }
        parent::__construct($definition);
        // Always add a default 'uuid' key.
        $this->entity_keys['uuid'] = 'uuid';
        $this->entity_keys['langcode'] = 'langcode';
        $this->handlers += ['storage' => \Drupal\Core\Config\Entity\Config_Entity_Storage::class];
        $this->lookup_keys[] = 'uuid';
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_prefix(): string
    {
        // Ensure that all configuration entities are prefixed by the name of the
        // module that provides the configuration entity type.
        if (isset($this->config_prefix)) {
            $config_prefix = $this->provider . '.' . $this->config_prefix;
        } else {
            $config_prefix = $this->provider . '.' . $this->id();
        }
        if (strlen($config_prefix) > static::PREFIX_LENGTH) {
            throw new Config_Prefix_Length_Exception("The configuration file name prefix {$config_prefix} exceeds the maximum character limit of " . static::PREFIX_LENGTH);
        }
        return $config_prefix;
    }
    /**
     * {@inheritdoc}
     */
    public function get_base_table(): null
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_revision_data_table(): null
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_revision_table(): null
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_data_table(): null
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_config_dependency_key(): string
    {
        return 'config';
    }
    /**
     * {@inheritdoc}
     *
     * @throws \Drupal\Core\Config\Entity\Exception\ConfigEntityStorageClassException
     *   Exception thrown when the provided class is not an instance of
     *   \Drupal\Core\Config\Entity\ConfigEntityStorage.
     *
     * @see \Drupal\Core\Config\Entity\ConfigEntityStorage
     */
    protected function check_storage_class($class)
    {
        if (!is_a($class, 'Drupal\Core\Config\Entity\ConfigEntityStorage', true)) {
            throw new Config_Entity_Storage_Class_Exception("{$class} is not \\Drupal\\Core\\Config\\Entity\\ConfigEntityStorage or it does not extend it");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function get_properties_to_export($id = null)
    {
        // @todo https://www.drupal.org/project/drupal/issues/3113620 Make the
        //   config_export annotation required earlier, remove the possibility of
        //   returning NULL and deprecate the $id argument.
        if (!empty($this->merged_config_export)) {
            return $this->merged_config_export;
        }
        if (!empty($this->config_export)) {
            // Always add default properties to be exported.
            $this->merged_config_export = ['uuid' => 'uuid', 'langcode' => 'langcode', 'status' => 'status', 'dependencies' => 'dependencies', 'third_party_settings' => 'third_party_settings', '_core' => '_core'];
            foreach ($this->config_export as $property => $name) {
                if (is_numeric($property)) {
                    $this->merged_config_export[$name] = $name;
                } else {
                    $this->merged_config_export[$property] = $name;
                }
            }
        } else {
            return null;
        }
        return $this->merged_config_export;
    }
    /**
     * {@inheritdoc}
     */
    public function get_lookup_keys()
    {
        return $this->lookup_keys;
    }
    /**
     * {@inheritdoc}
     */
    public function get_constraints()
    {
        $constraints = parent::get_constraints();
        // If there is an ID key for this config entity type, make it immutable by
        // default. Individual config entities can override this with an
        // `ImmutableProperties` constraint in their definition that is either
        // empty, or with an alternative set of immutable properties.
        $id_key = $this->get_key('id');
        if ($id_key) {
            $constraints += ['ImmutableProperties' => ['properties' => [$id_key]]];
        }
        return $constraints;
    }
}