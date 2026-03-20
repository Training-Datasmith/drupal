<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

/**
 * Defines a common interface for all content entity objects.
 *
 * Content entities use fields for all their entity properties and can be
 * translatable and revisionable. Translations and revisions can be
 * enabled per entity type through annotation and using entity type hooks.
 *
 * It's best practice to always implement ContentEntityInterface for
 * content-like entities that should be stored in some database, and
 * enable/disable revisions and translations as desired.
 *
 * When implementing this interface which extends Traversable, make sure to list
 * IteratorAggregate or Iterator before this interface in the implements clause.
 *
 * @see \Drupal\Core\Entity\ContentEntityBase
 * @see \Drupal\Core\Entity\EntityTypeInterface
 *
 * @ingroup entity_api
 */
interface Content_Entity_Interface extends \Traversable, Fieldable_Entity_Interface, Translatable_Revisionable_Interface, Synchronizable_Interface
{
    /**
     * Gets the bundle entity of this entity.
     *
     * @return \Drupal\Core\Entity\EntityInterface|null
     *   The entity which is the bundle of this entity, or NULL if this entity's
     *   entity type does not represent bundles with an entity.
     */
    public function get_bundle_entity(): ?Entity_Interface;
}