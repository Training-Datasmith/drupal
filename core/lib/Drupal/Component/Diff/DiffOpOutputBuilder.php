<?php

declare (strict_types=1);
namespace Drupal\Component\Diff;

use Drupal\Component\Diff\Engine\Diff_Op;
use Drupal\Component\Diff\Engine\Diff_Op_Add;
use Drupal\Component\Diff\Engine\Diff_Op_Change;
use Drupal\Component\Diff\Engine\Diff_Op_Copy;
use Drupal\Component\Diff\Engine\Diff_Op_Delete;
use Sebastian_Bergmann\Diff\Differ;
use Sebastian_Bergmann\Diff\Output\Diff_Output_Builder_Interface;
/**
 * Returns a diff as an array of DiffOp operations.
 */
final class Diff_Op_Output_Builder implements Diff_Output_Builder_Interface
{
    /**
     * A constant to manage removal+addition as a single operation.
     */
    private const CHANGED = 999;
    /**
     * {@inheritdoc}
     */
    public function get_diff(array $diff): string
    {
        return serialize($this->to_ops_array($diff));
    }
    /**
     * Converts the output of Differ to an array of DiffOp* value objects.
     *
     * @param array $diff
     *   The array output of Differ::diffToArray().
     *
     * @return \Drupal\Component\Diff\Engine\DiffOp[]
     *   An array of DiffOp* value objects.
     */
    public function to_ops_array(array $diff): array
    {
        $ops = [];
        $hunk_mode = null;
        $hunk_source = [];
        $hunk_target = [];
        for ($i = 0; $i < count($diff); $i++) {
            // Handle a sequence of removals + additions as a sequence of changes, and
            // manages the tail if required.
            if ($diff[$i][1] === Differ::REMOVED) {
                if ($hunk_mode !== null) {
                    $ops[] = $this->hunk_op($hunk_mode, $hunk_source, $hunk_target);
                    $hunk_source = [];
                    $hunk_target = [];
                }
                for ($n = $i; $n < count($diff) && $diff[$n][1] === Differ::REMOVED; $n++) {
                    $hunk_source[] = $diff[$n][0];
                }
                for (; $n < count($diff) && $diff[$n][1] === Differ::ADDED; $n++) {
                    $hunk_target[] = $diff[$n][0];
                }
                if (count($hunk_target) === 0) {
                    $ops[] = $this->hunk_op(Differ::REMOVED, $hunk_source, $hunk_target);
                } else {
                    $ops[] = $this->hunk_op(self::CHANGED, $hunk_source, $hunk_target);
                }
                $hunk_mode = null;
                $hunk_source = [];
                $hunk_target = [];
                $i = $n - 1;
                continue;
            }
            // When here, we are adding or copying the item. Removing or changing is
            // managed above.
            if ($hunk_mode === null) {
                $hunk_mode = $diff[$i][1];
            } elseif ($hunk_mode !== $diff[$i][1]) {
                $ops[] = $this->hunk_op($hunk_mode, $hunk_source, $hunk_target);
                $hunk_mode = $diff[$i][1];
                $hunk_source = [];
                $hunk_target = [];
            }
            $hunk_source[] = $diff[$i][0];
        }
        if ($hunk_mode !== null) {
            $ops[] = $this->hunk_op($hunk_mode, $hunk_source, $hunk_target);
        }
        return $ops;
    }
    /**
     * Returns the proper DiffOp object based on the hunk mode.
     *
     * @param int $mode
     *   A Differ constant or self::CHANGED.
     * @param string[] $source
     *   An array of strings to be changed/added/removed/copied.
     * @param string[] $source
     *   The array of strings to be changed to when self::CHANGED is specified.
     *
     * @return \Drupal\Component\Diff\Engine\DiffOp
     *   A DiffOp* value object.
     *
     * @throw \InvalidArgumentException
     *   When $mode is not valid.
     */
    private function hunk_op(int $mode, array $source, array $target): Diff_Op
    {
        return match ($mode) {
            Differ::OLD => new Diff_Op_Copy($source),
            self::CHANGED => new Diff_Op_Change($source, $target),
            Differ::ADDED => new Diff_Op_Add($source),
            Differ::REMOVED => new Diff_Op_Delete($source),
            default => throw new \InvalidArgumentException("Invalid \$mode {$mode} specified"),
        };
    }
}