<?php

declare (strict_types=1);
namespace Drupal\Component\Diff\Engine;

/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Diff_Op_Copy extends Diff_Op
{
    public $type = 'copy';
    public function __construct($orig, $closing = false)
    {
        if (!is_array($closing)) {
            $closing = $orig;
        }
        $this->orig = $orig;
        $this->closing = $closing;
    }
}