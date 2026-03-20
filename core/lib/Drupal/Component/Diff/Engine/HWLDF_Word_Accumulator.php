<?php

declare (strict_types=1);
namespace Drupal\Component\Diff\Engine;

/**
 * Additions by Axel Boldt follow, partly taken from diff.php, phpwiki-1.3.3
 */
/**
 * @todo document
 * @private
 * @subpackage DifferenceEngine
 */
class Hwldf_Word_Accumulator
{
    /**
     * An iso-8859-x non-breaking space.
     */
    public const NBSP = '&#160;';
    protected $lines = [];
    protected $line = '';
    protected $group = '';
    protected $tag = '';
    protected function _flush_group($new_tag)
    {
        if ($this->group !== '') {
            if ($this->tag == 'mark') {
                $this->line = $this->line . '<span class="diffchange">' . $this->group . '</span>';
            } else {
                $this->line = $this->line . $this->group;
            }
        }
        $this->group = '';
        $this->tag = $new_tag;
    }
    protected function _flush_line($new_tag)
    {
        $this->_flush_group($new_tag);
        if ($this->line != '') {
            array_push($this->lines, $this->line);
        } else {
            // make empty lines visible by inserting an NBSP
            array_push($this->lines, $this::NBSP);
        }
        $this->line = '';
    }
    public function add_words($words, $tag = ''): void
    {
        if ($tag != $this->tag) {
            $this->_flush_group($tag);
        }
        foreach ($words as $word) {
            // new-line should only come as first char of word.
            if ($word == '') {
                continue;
            }
            if ($word[0] == "\n") {
                $this->_flush_line($tag);
                $word = mb_substr((string) $word, 1);
            }
            assert(!str_contains((string) $word, "\n"));
            $this->group .= $word;
        }
    }
    public function get_lines()
    {
        $this->_flush_line('~done');
        return $this->lines;
    }
}