<?php

declare(strict_types=1);

namespace Drupal\taxonomy_crud\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Hook implementations for taxonomy_crud.
 */
class TaxonomyCrudHooks
{
    /**
     * Implements hook_ENTITY_TYPE_presave() for taxonomy_vocabulary entities.
     */
    #[Hook('taxonomy_vocabulary_presave')]
    public function taxonomyVocabularyPresave(VocabularyInterface $vocabulary): void
    {
        $vocabulary->setThirdPartySetting('taxonomy_crud', 'foo', 'bar');
    }

}
