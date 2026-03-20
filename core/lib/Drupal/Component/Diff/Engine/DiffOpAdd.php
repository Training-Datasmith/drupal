<?php

declare (strict_types=1);
namespace Drupal\Component\Diff\Engine;

/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Diff_Op_Add extends Diff_Op
{
    public $type = 'add';
    public function __construct($lines)
    {
        $this->closing = $lines;
        $this->orig = false;
    }
}