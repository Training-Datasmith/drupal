<?php

namespace Drupal\migrate\Plugin\migrate\id_map;

use Drupal\Component\Plugin\Attribute\PluginID;
use Drupal\Core\Plugin\PluginBase;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Defines the null ID map implementation.
 *
 * This serves as a dummy in order to not store anything.
 */
#[PluginID('null')]
class NullIdMap extends PluginBase implements MigrateIdMapInterface {

  /**
   * {@inheritdoc}
   */
  public function setMessage(MigrateMessageInterface $message): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getRowBySource(array $source_id_values): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRowByDestination(array $destination_id_values): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRowsNeedingUpdate($count): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupSourceId(array $destination_id_values): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function lookupDestinationIds(array $source_id_values): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function saveIdMapping(Row $row, array $destination_id_values, $source_row_status = MigrateIdMapInterface::STATUS_IMPORTED, $rollback_action = MigrateIdMapInterface::ROLLBACK_DELETE): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessage(array $source_id_values, $message, $level = MigrationInterface::MESSAGE_ERROR): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages(array $source_id_values = [], $level = NULL): \ArrayIterator {
    return new \ArrayIterator([]);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareUpdate(): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function processedCount(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function importedCount(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function updateCount(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function errorCount(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function messageCount(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $source_id_values, $messages_only = FALSE): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDestination(array $destination_id_values): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function setUpdate(array $source_id_values): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function clearMessages(): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function destroy(): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function currentDestination(): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function currentSource(): null {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getQualifiedMapTableName(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function key(): mixed {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return FALSE;
  }

}
