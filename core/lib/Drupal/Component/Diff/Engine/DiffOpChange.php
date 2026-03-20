<?php

declare (strict_types=1);
namespace Drupal\Component\Diff\Engine;

/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Diff_Op_Change extends Diff_Op
{
    public $type = 'change';
    public function __construct($orig, $closing)
    {
        $this->orig = $orig;
        $this->closing = $closing;
    }
}