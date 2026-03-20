<?php

declare (strict_types=1);
// phpcs:ignoreFile
/**
 * @file
 *
 * This class is a near-copy of Doctrine\Common\Annotations\TokenParser, which
 * is part of the Doctrine project: <http://www.doctrine-project.org>. It was
 * copied from version 2.0.2.
 *
 * Original copyright:
 *
 * Copyright (c) 2006-2013 Doctrine Project
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 */
namespace Drupal\Component\Annotation\Doctrine;

use function array_merge;
use function count;
use function explode;
use const PHP_VERSION_ID;
use function strtolower;
use const T_AS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_USE;
use const T_WHITESPACE;
use function token_get_all;
/**
 * Parses a file for namespaces/use/class declarations.
 */
class Token_Parser
{
    /**
     * The token list.
     *
     * @phpstan-var list<mixed[]>
     */
    private array $tokens;
    /**
     * The number of tokens.
     */
    private readonly int $num_tokens;
    /**
     * The current array pointer.
     */
    private int $pointer = 0;
    public function __construct(string $contents)
    {
        $this->tokens = token_get_all($contents);
        // The PHP parser sets internal compiler globals for certain things. Annoyingly, the last docblock comment it
        // saw gets stored in doc_comment. When it comes to compile the next thing to be include()d this stored
        // doc_comment becomes owned by the first thing the compiler sees in the file that it considers might have a
        // docblock. If the first thing in the file is a class without a doc block this would cause calls to
        // getDocBlock() on said class to return our long lost doc_comment.
        // To workaround, cause the parser to parse an empty docblock. Sure getDocBlock() will return this, but at least
        // it's harmless to us.
        token_get_all("<?php\n/**\n *\n */");
        $this->num_tokens = count($this->tokens);
    }
    /**
     * Gets the next non whitespace and non comment token.
     *
     * @param bool $docCommentIsComment If TRUE then a doc comment is considered a comment and skipped.
     * If FALSE then only whitespace and normal comments are skipped.
     *
     * @return mixed[]|string|null The token if exists, null otherwise.
     */
    public function next(bool $doc_comment_is_comment = true)
    {
        for ($i = $this->pointer; $i < $this->num_tokens; $i++) {
            $this->pointer++;
            if ($this->tokens[$i][0] === T_WHITESPACE) {
                continue;
            }
            if ($this->tokens[$i][0] === T_COMMENT) {
                continue;
            }
            if ($doc_comment_is_comment && $this->tokens[$i][0] === T_DOC_COMMENT) {
                continue;
            }
            return $this->tokens[$i];
        }
        return null;
    }
    /**
     * Parses a single use statement.
     *
     * @return array<string, string> A list with all found class names for a use statement.
     */
    public function parse_use_statement(): array
    {
        $group_root = '';
        $class = '';
        $alias = '';
        $statements = [];
        $explicit_alias = false;
        while ($token = $this->next()) {
            if (!$explicit_alias && $token[0] === T_STRING) {
                $class .= $token[1];
                $alias = $token[1];
            } elseif ($explicit_alias && $token[0] === T_STRING) {
                $alias = $token[1];
            } elseif (PHP_VERSION_ID >= 80000 && ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED)) {
                $class .= $token[1];
                $class_split = explode('\\', (string) $token[1]);
                $alias = $class_split[count($class_split) - 1];
            } elseif ($token[0] === T_NS_SEPARATOR) {
                $class .= '\\';
                $alias = '';
            } elseif ($token[0] === T_AS) {
                $explicit_alias = true;
                $alias = '';
            } elseif ($token === ',') {
                $statements[strtolower($alias)] = $group_root . $class;
                $class = '';
                $alias = '';
                $explicit_alias = false;
            } elseif ($token === ';') {
                $statements[strtolower($alias)] = $group_root . $class;
                break;
            } elseif ($token === '{') {
                $group_root = $class;
                $class = '';
            } elseif ($token === '}') {
                continue;
            } else {
                break;
            }
        }
        return $statements;
    }
    /**
     * Gets all use statements.
     *
     * @param string $namespaceName The namespace name of the reflected class.
     *
     * @return array<string, string> A list with all found use statements.
     */
    public function parse_use_statements(string $namespace_name): array
    {
        $statements = [];
        while ($token = $this->next()) {
            if ($token[0] === T_USE) {
                $statements = array_merge($statements, $this->parse_use_statement());
                continue;
            }
            if ($token[0] !== T_NAMESPACE) {
                continue;
            }
            if ($this->parse_namespace() !== $namespace_name) {
                continue;
            }
            // Get fresh array for new namespace. This is to prevent the parser to collect the use statements
            // for a previous namespace with the same name. This is the case if a namespace is defined twice
            // or if a namespace with the same name is commented out.
            $statements = [];
        }
        return $statements;
    }
    /**
     * Gets the namespace.
     *
     * @return string The found namespace.
     */
    public function parse_namespace(): string
    {
        $name = '';
        while (($token = $this->next()) && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR || PHP_VERSION_ID >= 80000 && ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED))) {
            $name .= $token[1];
        }
        return $name;
    }
    /**
     * Gets the class name.
     *
     * @return string The found class name.
     */
    public function parse_class()
    {
        // Namespaces and class names are tokenized the same: T_STRINGs
        // separated by T_NS_SEPARATOR so we can use one function to provide
        // both.
        return $this->parse_namespace();
    }
}