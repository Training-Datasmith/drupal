<?php

declare (strict_types=1);
namespace Drupal\Core\Config\Checkpoint;

use Drupal\Component\Datetime\Time_Interface;
use Drupal\Core\State\State_Interface;
/**
 * A chronological list of Checkpoint objects.
 *
 * @internal
 *   This API is experimental.
 */
final class Linear_History implements Checkpoint_List_Interface
{
    /**
     * The store of all the checkpoint names in state.
     */
    private const CHECKPOINT_KEY = 'config.checkpoints';
    /**
     * The active checkpoint.
     *
     * In our implementation this is always the last in the list.
     */
    private ?Checkpoint $active_checkpoint;
    /**
     * The list of checkpoints, keyed by ID.
     *
     * @var \Drupal\Core\Config\Checkpoint\Checkpoint[]
     */
    private array $checkpoints;
    /**
     * Constructs a checkpoints object.
     *
     * @param \Drupal\Core\State\StateInterface $state
     *   The state service.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(private readonly State_Interface $state, private readonly Time_Interface $time)
    {
        $this->checkpoints = $this->state->get(self::CHECKPOINT_KEY, []);
        $this->active_checkpoint = end($this->checkpoints) ?: null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_active_checkpoint(): ?Checkpoint
    {
        return $this->active_checkpoint;
    }
    /**
     * {@inheritdoc}
     */
    public function get(string $id): Checkpoint
    {
        if (!isset($this->checkpoints[$id])) {
            throw new Unknown_Checkpoint_Exception(sprintf('The checkpoint "%s" does not exist', $id));
        }
        return $this->checkpoints[$id];
    }
    /**
     * {@inheritdoc}
     */
    public function get_parents(string $id): \Traversable
    {
        if (!isset($this->checkpoints[$id])) {
            throw new Unknown_Checkpoint_Exception(sprintf('The checkpoint "%s" does not exist', $id));
        }
        $checkpoint = $this->checkpoints[$id];
        while ($checkpoint->parent !== null) {
            $checkpoint = $this->get($checkpoint->parent);
            yield $checkpoint->id => $checkpoint;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->checkpoints);
    }
    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->checkpoints);
    }
    /**
     * {@inheritdoc}
     */
    public function add(string $id, string|\Stringable $label): Checkpoint
    {
        if (isset($this->checkpoints[$id])) {
            throw new Checkpoint_Exists_Exception(sprintf('Cannot create a checkpoint with the ID "%s" as it already exists', $id));
        }
        $checkpoint = new Checkpoint($id, $label, $this->time->get_current_time(), $this->active_checkpoint?->id);
        $this->checkpoints[$checkpoint->id] = $checkpoint;
        $this->active_checkpoint = $checkpoint;
        $this->state->set(self::CHECKPOINT_KEY, $this->checkpoints);
        return $checkpoint;
    }
    /**
     * {@inheritdoc}
     */
    public function delete(string $id): static
    {
        if (!isset($this->checkpoints[$id])) {
            throw new Unknown_Checkpoint_Exception(sprintf('Cannot delete a checkpoint with the ID "%s" as it does not exist', $id));
        }
        foreach ($this->checkpoints as $key => $checkpoint) {
            unset($this->checkpoints[$key]);
            if ($checkpoint->id === $id) {
                break;
            }
        }
        $first = reset($this->checkpoints);
        if ($first instanceof Checkpoint) {
            // Make sure the first checkpoint does not have a parent set.
            $fixed = new Checkpoint($first->id, $first->label, $first->timestamp, null);
            $this->checkpoints[$fixed->id] = $fixed;
        }
        $this->active_checkpoint = end($this->checkpoints) ?: null;
        if (!empty($this->checkpoints)) {
            $this->state->set(self::CHECKPOINT_KEY, $this->checkpoints);
        } else {
            $this->state->delete(self::CHECKPOINT_KEY);
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function delete_all(): static
    {
        $this->checkpoints = [];
        $this->active_checkpoint = null;
        $this->state->delete(self::CHECKPOINT_KEY);
        return $this;
    }
}