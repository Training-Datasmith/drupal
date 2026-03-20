<?php

declare (strict_types=1);
namespace Drupal\Component\Diff\Engine;

/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Diff_Op_Delete extends Diff_Op
{
    public $type = 'delete';
    public function __construct($lines)
    {
        $this->orig = $lines;
        $this->closing = false;
    }
}