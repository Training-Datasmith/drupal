<?php

declare (strict_types=1);
namespace Drupal\Component\Diff\Engine;

/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Diff_Op
{
    public $type;
    public $orig;
    public $closing;
}