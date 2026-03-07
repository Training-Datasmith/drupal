<?php

declare(strict_types=1);

namespace Drupal\block_content;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;

/**
 * Provides an interface defining a block type entity.
 */
interface BlockContentTypeInterface extends ConfigEntityInterface, RevisionableEntityBundleInterface, EntityDescriptionInterface
{
}
