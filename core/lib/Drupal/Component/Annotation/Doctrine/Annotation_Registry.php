<?php

declare (strict_types=1);
// phpcs:ignoreFile
/**
 * @file
 *
 * This class is a near-copy of Doctrine\Common\Annotations\AnnotationRegistry,
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

use function array_key_exists;
use function class_exists;
final class Annotation_Registry
{
    /**
     * An array of classes which cannot be found
     *
     * @var null[] indexed by class name
     */
    private static array $failed_to_autoload = [];
    public static function reset(): void
    {
        self::$failed_to_autoload = [];
    }
    /**
     * Autoload an annotation class silently.
     */
    public static function load_annotation_class(string $class): bool
    {
        if (class_exists($class, false)) {
            return true;
        }
        if (array_key_exists($class, self::$failed_to_autoload)) {
            return false;
        }
        if (class_exists($class)) {
            return true;
        }
        self::$failed_to_autoload[$class] = null;
        return false;
    }
}