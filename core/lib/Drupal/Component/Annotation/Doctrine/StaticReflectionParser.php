<?php

declare (strict_types=1);
// phpcs:ignoreFile
// cspell:ignore paamayim nekudotayim
/**
 * @file
 *
 * This class is a near-copy of
 * Doctrine\Common\Reflection\StaticReflectionParser, which is part of the
 * Doctrine project: <http://www.doctrine-project.org>. It was copied from
 * version 1.2.2.
 *
 * Original copyright:
 *
 * Copyright (c) 2006-2015 Doctrine Project
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
use function file_get_contents;
use function is_array;
use function ltrim;
use function preg_match;
use Reflection_Exception;
use function sprintf;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use const T_CLASS;
use const T_DOC_COMMENT;
use const T_EXTENDS;
use const T_FUNCTION;
use const T_NEW;
use const T_PAAMAYIM_NEKUDOTAYIM;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_STRING;
use const T_USE;
use const T_VAR;
use const T_VARIABLE;
/**
 * Parses a file for namespaces/use/class declarations.
 */
class Static_Reflection_Parser
{
    /**
     * The fully qualified class name.
     */
    protected string $class_name;
    /**
     * The short class name.
     */
    protected string $short_class_name;
    /**
     * A ClassFinder object which finds the class.
     *
     * @var ClassFinderInterface
     */
    protected $finder;
    /**
     * Whether the parser has run.
     *
     * @var bool
     */
    protected $parsed = false;
    /**
     * The namespace of the class.
     */
    protected string $namespace = '';
    /**
     * The use statements of the class.
     *
     * @var string[]
     */
    protected $use_statements = [];
    /**
     * The docComment of the class.
     *
     * @var mixed[]
     */
    protected $doc_comment = ['class' => '', 'property' => [], 'method' => []];
    /**
     * The name of the class this class extends, if any.
     *
     * @var string
     */
    protected $parent_class_name = '';
    /**
     * The parent PSR-0 Parser.
     *
     * @var \Doctrine\Common\Reflection\StaticReflectionParser
     */
    protected $parent_static_reflection_parser;
    /**
     * The class attributes.
     *
     * @var string[]
     */
    protected array $class_attributes = [];
    /**
     * Method attributes
     *
     * @var string[][]
     */
    protected array $method_attributes = [];
    /**
     * Parses a class residing in a PSR-0 hierarchy.
     *
     * @param string               $className               The full, namespaced class name.
     * @param ClassFinderInterface $finder                  A ClassFinder object which finds the class.
     * @param bool                 $classAnnotationOptimize Only retrieve the class docComment.
     *                                                         Presumes there is only one statement per line.
     */
    public function __construct(
        $class_name,
        $finder,
        /**
         * Whether the caller only wants class annotations.
         */
        protected $class_annotation_optimize = false
    )
    {
        $this->class_name = ltrim($class_name, '\\');
        $last_ns_pos = strrpos($this->class_name, '\\');
        if ($last_ns_pos !== false) {
            $this->namespace = substr($this->class_name, 0, $last_ns_pos);
            $this->short_class_name = substr($this->class_name, $last_ns_pos + 1);
        } else {
            $this->short_class_name = $this->class_name;
        }
        $this->finder = $finder;
    }
    /**
     * @return void
     */
    protected function parse()
    {
        $file_name = $this->finder->find_file($this->class_name);
        if ($this->parsed || !$file_name) {
            return;
        }
        $this->parsed = true;
        $contents = file_get_contents($file_name);
        if ($this->class_annotation_optimize) {
            $regex = sprintf('/\A.*^\s*((abstract|final)\s+)?class\s+%s\s+/sm', $this->short_class_name);
            if (preg_match($regex, $contents, $matches)) {
                $contents = $matches[0];
            }
        }
        $token_parser = new Token_Parser($contents);
        $doc_comment = '';
        $last_token = false;
        $attribute_names = [];
        while ($token = $token_parser->next(false)) {
            switch ($token[0]) {
                case T_USE:
                    $this->use_statements = array_merge($this->use_statements, $token_parser->parse_use_statement());
                    break;
                case T_DOC_COMMENT:
                    $doc_comment = $token[1];
                    break;
                case T_ATTRIBUTE:
                    while ($token = $token_parser->next()) {
                        if ($token[0] === T_NAME_FULLY_QUALIFIED || $token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_RELATIVE || $token[0] === T_STRING) {
                            $attribute_names[] = $token[1];
                            break 2;
                        }
                    }
                    break;
                case T_CLASS:
                    // Convert the attributes to fully qualified names.
                    $this->class_attributes = array_map($this->fully_specify_name(...), $attribute_names);
                    if ($last_token !== T_PAAMAYIM_NEKUDOTAYIM && $last_token !== T_NEW) {
                        $this->doc_comment['class'] = $doc_comment;
                        $doc_comment = '';
                        $attribute_names = [];
                    }
                    break;
                case T_VAR:
                case T_PRIVATE:
                case T_PROTECTED:
                case T_PUBLIC:
                    $token = $token_parser->next();
                    if ($token[0] === T_VARIABLE) {
                        $property_name = substr((string) $token[1], 1);
                        $this->doc_comment['property'][$property_name] = $doc_comment;
                        $attribute_names = [];
                        continue 2;
                    }
                    if ($token[0] !== T_FUNCTION) {
                        // For example, it can be T_FINAL.
                        continue 2;
                    }
                // no break.
                case T_FUNCTION:
                    // The next string after function is the name, but
                    // there can be & before the function name so find the
                    // string.
                    while (($token = $token_parser->next()) && $token[0] !== T_STRING) {
                    }
                    if ($token === null) {
                        break;
                    }
                    $method_name = $token[1];
                    $this->doc_comment['method'][$method_name] = $doc_comment;
                    $doc_comment = '';
                    $this->method_attributes[$method_name] = array_map($this->fully_specify_name(...), $attribute_names);
                    $attribute_names = [];
                    break;
                case T_EXTENDS:
                    $this->parent_class_name = $this->fully_specify_name($token_parser->parse_class());
                    break;
            }
            $last_token = is_array($token) ? $token[0] : false;
        }
    }
    /**
     * @return StaticReflectionParser
     */
    protected function get_parent_static_reflection_parser()
    {
        if (empty($this->parent_static_reflection_parser)) {
            $this->parent_static_reflection_parser = new static($this->parent_class_name, $this->finder);
        }
        return $this->parent_static_reflection_parser;
    }
    /**
     * @return string
     */
    public function get_class_name()
    {
        return $this->class_name;
    }
    /**
     * @return string
     */
    public function get_namespace_name()
    {
        return $this->namespace;
    }
    /**
     * Gets the ReflectionClass equivalent for this class.
     *
     * @return ReflectionClass
     */
    public function get_reflection_class(): \Drupal\Component\Annotation\Doctrine\Static_Reflection_Class
    {
        return new Static_Reflection_Class($this);
    }
    /**
     * Gets the use statements from this file.
     *
     * @return string[]
     */
    public function get_use_statements()
    {
        $this->parse();
        return $this->use_statements;
    }
    /**
     * Gets the doc comment.
     *
     * @param string $type The type: 'class', 'property' or 'method'.
     * @param string $name The name of the property or method, not needed for 'class'.
     *
     * @return string The doc comment, empty string if none.
     */
    public function get_doc_comment($type = 'class', $name = '')
    {
        $this->parse();
        return $name ? $this->doc_comment[$type][$name] : $this->doc_comment[$type];
    }
    public function get_method_attributes(): array
    {
        $this->parse();
        return $this->method_attributes;
    }
    /**
     * Gets the PSR-0 parser for the declaring class.
     *
     * @param string $type The type: 'property' or 'method'.
     * @param string $name The name of the property or method.
     *
     * @return StaticReflectionParser A static reflection parser for the declaring class.
     *
     * @throws ReflectionException
     */
    public function get_static_reflection_parser_for_declaring_class(string $type, string $name)
    {
        $this->parse();
        if (isset($this->doc_comment[$type][$name])) {
            return $this;
        }
        if (!empty($this->parent_class_name)) {
            return $this->get_parent_static_reflection_parser()->get_static_reflection_parser_for_declaring_class($type, $name);
        }
        throw new Reflection_Exception('Invalid ' . $type . ' "' . $name . '"');
    }
    /**
     * Determines if the class has the provided class attribute.
     *
     * @param string $attribute The fully qualified attribute to check for.
     */
    public function has_class_attribute(string $attribute): bool
    {
        $this->parse();
        return static::has_attribute($this->class_attributes, $attribute);
    }
    public static function has_attribute(array $existing_attributes, string $attribute_looking_for): bool
    {
        foreach ($existing_attributes as $existing_attribute) {
            if (is_a($existing_attribute, $attribute_looking_for, true)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Converts a name into a fully specified name.
     *
     * @param string $name The name to convert.
     */
    private function fully_specify_name(string $name): string
    {
        $ns_pos = strpos($name, '\\');
        $fully_specified = false;
        if ($ns_pos === 0) {
            $fully_specified = true;
        } else {
            if ($ns_pos) {
                $prefix = strtolower(substr($name, 0, $ns_pos));
                $postfix = substr($name, $ns_pos);
            } else {
                $prefix = strtolower($name);
                $postfix = '';
            }
            foreach ($this->use_statements as $alias => $use) {
                if ($alias !== $prefix) {
                    continue;
                }
                $name = '\\' . $use . $postfix;
                $fully_specified = true;
            }
        }
        if (!$fully_specified) {
            return '\\' . $this->namespace . '\\' . $name;
        }
        return $name;
    }
}