<?php

declare (strict_types=1);
namespace Drupal\Component\Diff;

use Drupal\Component\Diff\Engine\Hwldf_Word_Accumulator;
/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Word_Level_Diff extends Mapped_Diff
{
    public const MAX_LINE_LENGTH = 10000;
    public function __construct($orig_lines, $closing_lines)
    {
        [$orig_words, $orig_stripped] = $this->_split($orig_lines);
        [$closing_words, $closing_stripped] = $this->_split($closing_lines);
        parent::__construct($orig_words, $closing_words, $orig_stripped, $closing_stripped);
    }
    protected function _split($lines): array
    {
        $words = [];
        $stripped = [];
        $first = true;
        foreach ($lines as $line) {
            // If the line is too long, just pretend the entire line is one big word
            // This prevents resource exhaustion problems
            if ($first) {
                $first = false;
            } else {
                $words[] = "\n";
                $stripped[] = "\n";
            }
            if (mb_strlen((string) $line) > $this::MAX_LINE_LENGTH) {
                $words[] = $line;
                $stripped[] = $line;
            } else if (preg_match_all('/ ( [^\S\n]+ | [0-9_A-Za-z\x80-\xff]+ | . ) (?: (?!< \n) [^\S\n])? /xs', (string) $line, $m)) {
                $words = array_merge($words, $m[0]);
                $stripped = array_merge($stripped, $m[1]);
            }
        }
        return [$words, $stripped];
    }
    public function orig()
    {
        $orig = new Hwldf_Word_Accumulator();
        foreach ($this->edits as $edit) {
            if ($edit->type == 'copy') {
                $orig->add_words($edit->orig);
            } elseif ($edit->orig) {
                $orig->add_words($edit->orig, 'mark');
            }
        }
        return $orig->get_lines();
    }
    public function closing()
    {
        $closing = new Hwldf_Word_Accumulator();
        foreach ($this->edits as $edit) {
            if ($edit->type == 'copy') {
                $closing->add_words($edit->closing);
            } elseif ($edit->closing) {
                $closing->add_words($edit->closing, 'mark');
            }
        }
        return $closing->get_lines();
    }
}