<?php

declare (strict_types=1);
namespace Drupal\Core\Diff;

use Drupal\Component\Diff\Diff_Formatter as DiffFormatterBase;
use Drupal\Component\Diff\Word_Level_Diff;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Config_Factory_Interface;
// cspell:ignore xbeg, xlen, ybeg, ylen
/**
 * Diff formatter which uses returns output that can be rendered to a table.
 */
class Diff_Formatter extends Diff_Formatter_Base
{
    /**
     * The diff represented as an array of rows.
     *
     * @var array
     */
    protected $rows = [];
    /**
     * Creates a DiffFormatter to render diffs in a table.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The config factory.
     */
    public function __construct(Config_Factory_Interface $config_factory)
    {
        $config = $config_factory->get('system.diff');
        $this->leading_context_lines = $config->get('context.lines_leading');
        $this->trailing_context_lines = $config->get('context.lines_trailing');
    }
    /**
     * {@inheritdoc}
     */
    protected function _start_diff()
    {
        $this->rows = [];
    }
    /**
     * {@inheritdoc}
     */
    protected function _end_diff(): string|false
    {
        return $this->rows;
    }
    /**
     * {@inheritdoc}
     */
    protected function _block_header($xbeg, $xlen, $ybeg, $ylen): string
    {
        return [['data' => $xbeg + $this->line_stats['offset']['x'], 'colspan' => 2], ['data' => $ybeg + $this->line_stats['offset']['y'], 'colspan' => 2]];
    }
    /**
     * {@inheritdoc}
     */
    protected function _start_block($header)
    {
        if ($this->show_header) {
            $this->rows[] = $header;
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function _lines($lines, $prefix = ' ', $color = 'white')
    {
    }
    /**
     * Creates an added line.
     *
     * @param string $line
     *   An HTML-escaped line.
     *
     * @return array
     *   An array representing a table row.
     */
    protected function added_line($line): array
    {
        return [['data' => '+', 'class' => 'diff-marker'], ['data' => ['#markup' => $line], 'class' => 'diff-context diff-addedline']];
    }
    /**
     * Creates a deleted line.
     *
     * @param string $line
     *   An HTML-escaped line.
     *
     * @return array
     *   An array representing a table row.
     */
    protected function deleted_line($line): array
    {
        return [['data' => '-', 'class' => 'diff-marker'], ['data' => ['#markup' => $line], 'class' => 'diff-context diff-deletedline']];
    }
    /**
     * Creates a context line.
     *
     * @param string $line
     *   An HTML-escaped line.
     *
     * @return array
     *   An array representing a table row.
     */
    protected function context_line($line): array
    {
        return [' ', ['data' => ['#markup' => $line], 'class' => 'diff-context']];
    }
    /**
     * Creates an empty line.
     *
     * @return array
     *   An array representing a table row.
     */
    protected function empty_line(): array
    {
        return [' ', ' '];
    }
    /**
     * {@inheritdoc}
     */
    protected function _added($lines)
    {
        foreach ($lines as $line) {
            $this->rows[] = array_merge($this->empty_line(), $this->added_line(Html::escape($line)));
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function _deleted($lines)
    {
        foreach ($lines as $line) {
            $this->rows[] = array_merge($this->deleted_line(Html::escape($line)), $this->empty_line());
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function _context($lines)
    {
        foreach ($lines as $line) {
            $this->rows[] = array_merge($this->context_line(Html::escape($line)), $this->context_line(Html::escape($line)));
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function _changed($orig, $closing)
    {
        $orig = array_map(\Drupal\Component\Utility\Html::escape(...), $orig);
        $closing = array_map(\Drupal\Component\Utility\Html::escape(...), $closing);
        $diff = new Word_Level_Diff($orig, $closing);
        $del = $diff->orig();
        $add = $diff->closing();
        // Notice that WordLevelDiff returns HTML-escaped output. Hence, we will be
        // calling addedLine/deletedLine without HTML-escaping.
        while ($line = array_shift($del)) {
            $aline = array_shift($add);
            $this->rows[] = array_merge($this->deleted_line($line), isset($aline) ? $this->added_line($aline) : $this->empty_line());
        }
        // If any leftovers.
        foreach ($add as $line) {
            $this->rows[] = array_merge($this->empty_line(), $this->added_line($line));
        }
    }
}