<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Entity;

use Drupal\Core\Cache\Cacheable_Metadata;
use Drupal\Core\Entity\Entity_Interface;
use Drupal\Core\Entity\Entity_List_Builder;
/**
 * Defines the default class to build a listing of configuration entities.
 *
 * @ingroup entity_api
 */
class Config_Entity_List_Builder extends Entity_List_Builder
{
    /**
     * The config entity storage class.
     *
     * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
     */
    protected $storage;
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $entity_ids = $this->get_entity_ids();
        $entities = $this->storage->load_multiple_override_free($entity_ids);
        // Sort the entities using the entity class's sort() method.
        // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
        uasort($entities, [$this->entity_type->get_class(), 'sort']);
        return $entities;
    }
    /**
     * {@inheritdoc}
     */
    protected function get_default_operations(Entity_Interface $entity)
    {
        $args = func_get_args();
        $cacheability = $args[1] ?? new Cacheable_Metadata();
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
        $operations = parent::get_default_operations($entity, $cacheability);
        if ($this->entity_type->has_key('status')) {
            if (!$entity->status() && $entity->has_link_template('enable')) {
                $operations['enable'] = ['title' => $this->t('Enable'), 'weight' => -10, 'url' => $this->ensure_destination($entity->to_url('enable'))];
            } elseif ($entity->has_link_template('disable')) {
                $operations['disable'] = ['title' => $this->t('Disable'), 'weight' => 40, 'url' => $this->ensure_destination($entity->to_url('disable'))];
            }
        }
        return $operations;
    }
    /**
     * Gets the config entity storage.
     *
     * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
     *   The config storage used by this list builder.
     */
    public function get_storage(): Config_Entity_Storage_Interface
    {
        return $this->storage;
    }
}