<?php

declare (strict_types=1);
namespace Drupal\Core\Entity;

use Drupal\Core\Field\Field_Definition_Listener_Interface;
use Drupal\Core\Field\Field_Storage_Definition_Listener_Interface;
/**
 * A storage that supports entity types with dynamic field definitions.
 *
 * A storage that implements this interface can react to the entity type's field
 * definitions changing, due to modules being installed or uninstalled, or via
 * field UI, or via code changes to the entity class.
 *
 * For example, configurable fields defined and exposed by field.module.
 */
interface Dynamically_Fieldable_Entity_Storage_Interface extends Fieldable_Entity_Storage_Interface, Field_Storage_Definition_Listener_Interface, Field_Definition_Listener_Interface
{
}