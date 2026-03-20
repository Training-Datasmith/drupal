<?php

declare (strict_types=1);
// phpcs:ignoreFile
// cspell:ignore optimizerplus
/**
 * @file
 *
 * This class is a near-copy of Doctrine\Common\Annotations\AnnotationException,
 * which is part of the Doctrine project: <http://www.doctrine-project.org>. It
 * was copied from version 2.0.2.
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

use Exception;
use function gettype;
use function implode;
use function is_object;
use function sprintf;
use Throwable;
/**
 * Description of AnnotationException
 */
class Annotation_Exception extends Exception
{
    /**
     * Creates a new AnnotationException describing a Syntax error.
     */
    public static function syntax_error(string $message): self
    {
        return new self('[Syntax Error] ' . $message);
    }
    /**
     * Creates a new AnnotationException describing a Semantical error.
     */
    public static function semantical_error(string $message): self
    {
        return new self('[Semantical Error] ' . $message);
    }
    /**
     * Creates a new AnnotationException describing an error which occurred during
     * the creation of the annotation.
     */
    public static function creation_error(string $message, ?Throwable $previous = null): self
    {
        return new self('[Creation Error] ' . $message, 0, $previous);
    }
    /**
     * Creates a new AnnotationException describing a type error.
     */
    public static function type_error(string $message): self
    {
        return new self('[Type Error] ' . $message);
    }
    /**
     * Creates a new AnnotationException describing a constant semantical error.
     *
     * @return AnnotationException
     */
    public static function semantical_error_constants(string $identifier, ?string $context = null)
    {
        return self::semantical_error(sprintf("Couldn't find constant %s%s.", $identifier, $context ? ', ' . $context : ''));
    }
    /**
     * Creates a new AnnotationException describing an type error of an attribute.
     *
     * @param mixed $actual
     *
     * @return AnnotationException
     */
    public static function attribute_type_error(string $attribute_name, string $annotation_name, string $context, string $expected, $actual)
    {
        return self::type_error(sprintf('Attribute "%s" of @%s declared on %s expects %s, but got %s.', $attribute_name, $annotation_name, $context, $expected, is_object($actual) ? 'an instance of ' . $actual::class : gettype($actual)));
    }
    /**
     * Creates a new AnnotationException describing an required error of an attribute.
     *
     * @return AnnotationException
     */
    public static function required_error(string $attribute_name, string $annotation_name, string $context, string $expected)
    {
        return self::type_error(sprintf('Attribute "%s" of @%s declared on %s expects %s. This value should not be null.', $attribute_name, $annotation_name, $context, $expected));
    }
    /**
     * Creates a new AnnotationException describing a invalid enumerator.
     *
     * @param mixed $given
     * @phpstan-param list<string> $available
     */
    public static function enumerator_error(string $attribute_name, string $annotation_name, string $context, array $available, $given): self
    {
        return new self(sprintf('[Enum Error] Attribute "%s" of @%s declared on %s accepts only [%s], but got %s.', $attribute_name, $annotation_name, $context, implode(', ', $available), is_object($given) ? $given::class : $given));
    }
    public static function optimizer_plus_save_comments(): self
    {
        return new self('You have to enable opcache.save_comments=1 or zend_optimizerplus.save_comments=1.');
    }
    public static function optimizer_plus_load_comments(): self
    {
        return new self('You have to enable opcache.load_comments=1 or zend_optimizerplus.load_comments=1.');
    }
}