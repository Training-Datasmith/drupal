<?php

declare(strict_types=1);

namespace Drupal\block_content;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the translation handler for content blocks.
 */
class BlockContentTranslationHandler extends ContentTranslationHandler
{
    /**
     * {@inheritdoc}
     */
    protected function entityFormTitle(EntityInterface $entity): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        $block_type = BlockContentType::load($entity->bundle());
        return $this->t('<em>Edit @type</em> @title', ['@type' => $block_type->label(), '@title' => $entity->label()]);
    }

}
