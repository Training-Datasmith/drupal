<?php

namespace Drupal\Core\Entity\KeyValueStore;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * Provides a key value backend for content entities.
 */
class KeyValueContentEntityStorage extends KeyValueEntityStorage implements ContentEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function createTranslation(ContentEntityInterface $entity, $langcode, array $values = []): void {
    // @todo Complete the content entity storage implementation in
    //   https://www.drupal.org/node/2618436.
  }

  /**
   * {@inheritdoc}
   */
  public function hasStoredTranslations(TranslatableInterface $entity): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createRevision(RevisionableInterface $entity, $default = TRUE, $keep_untranslatable_fields = NULL): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createWithSampleValues($bundle = FALSE, array $values = []) {}

  /**
   * {@inheritdoc}
   */
  public function loadMultipleRevisions(array $revision_ids): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadRevisionUnchanged($revision_id): ?EntityInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestRevisionId($entity_id): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestTranslationAffectedRevisionId($entity_id, $langcode): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadRevision($revision_id): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id): null {
    return NULL;
  }

}
