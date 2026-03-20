<?php

declare (strict_types=1);
namespace Drupal\Component\File_System;

/**
 * Iterates over files whose names match a regular expression in a directory.
 */
class Regex_Directory_Iterator extends \Regex_Iterator
{
    /**
     * RegexDirectoryIterator constructor.
     *
     * @param string $path
     *   The path to scan.
     * @param string $regex
     *   The regular expression to match, including delimiters. For example,
     *   /\.yml$/ would list only files ending in .yml.
     */
    public function __construct($path, $regex)
    {
        parent::__construct(new \Filesystem_Iterator($path), $regex);
    }
    /**
     * Implements \RegexIterator::accept().
     */
    public function accept(): bool
    {
        /** @var \SplFileInfo $file_info */
        $file_info = $this->get_inner_iterator()->current();
        return $file_info->is_file() && preg_match($this->get_regex(), $file_info->get_filename());
    }
}