<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Checkpoint;

/**
 * A value object to store information about a checkpoint.
 *
 * @internal
 *   This API is experimental.
 */
final readonly class Checkpoint {

  /**
   * Constructs a checkpoint object.
   *
   * @param string $id
   *   The checkpoint's ID.
   * @param \Stringable|string $label
   *   The human-readable label.
   * @param int $timestamp
   *   The timestamp when the checkpoint was created.
   * @param string|null $parent
   *   The ID of the checkpoint's parent.
   */
  public function __construct(
    public string $id,
    public \Stringable|string $label,
    public int $timestamp,
    public ?string $parent,
  ) {
  }

}
