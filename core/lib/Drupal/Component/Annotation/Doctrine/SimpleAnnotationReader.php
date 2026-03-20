<?php

declare (strict_types=1);
// phpcs:ignoreFile
/**
 * @file
 *
 * This class is a near-copy of
 * Doctrine\Common\Annotations\SimpleAnnotationReader, which is part of the
 * Doctrine project: <http://www.doctrine-project.org>. It was copied from
 * version 1.2.7.
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

/**
 * Simple Annotation Reader.
 *
 * Drupal adds its own version of DocParser and allows for ignoring common
 * annotations.
 *
 * @internal
 */
final class Simple_Annotation_Reader
{
    protected $ignored_annotations = ['addtogroup' => true, 'code' => true, 'defgroup' => true, 'deprecated' => true, 'endcode' => true, 'endlink' => true, 'file' => true, 'ingroup' => true, 'group' => true, 'link' => true, 'mainpage' => true, 'param' => true, 'ref' => true, 'return' => true, 'section' => true, 'see' => true, 'subsection' => true, 'throws' => true, 'todo' => true, 'var' => true, '{' => true, '}' => true];
    private readonly \Drupal\Component\Annotation\Doctrine\Doc_Parser $parser;
    /**
     * Constructor.
     *
     * Initializes a new SimpleAnnotationReader.
     */
    public function __construct()
    {
        $this->parser = new Doc_Parser();
        $this->parser->set_ignore_not_imported_annotations(true);
        $this->parser->set_ignored_annotation_names($this->ignored_annotations);
    }
    /**
     * Adds a namespace in which we will look for annotations.
     *
     * @param string $namespace
     */
    public function add_namespace($namespace): void
    {
        $this->parser->add_namespace($namespace);
    }
    /**
     * Gets the annotations applied to a class.
     *
     * @param ReflectionClass $class The ReflectionClass of the class from which
     * the class annotations should be read.
     *
     * @return array<object> An array of Annotations.
     */
    public function get_class_annotations(\ReflectionClass $class)
    {
        return $this->parser->parse($class->get_doc_comment(), 'class ' . $class->get_name());
    }
    /**
     * Gets the annotations applied to a method.
     *
     * @param ReflectionMethod $method The ReflectionMethod of the method from which
     * the annotations should be read.
     *
     * @return array<object> An array of Annotations.
     */
    public function get_method_annotations(\ReflectionMethod $method)
    {
        return $this->parser->parse($method->get_doc_comment(), 'method ' . $method->get_declaring_class()->name . '::' . $method->get_name() . '()');
    }
    /**
     * Gets the annotations applied to a property.
     *
     * @param ReflectionProperty $property The ReflectionProperty of the property
     * from which the annotations should be read.
     *
     * @return array<object> An array of Annotations.
     */
    public function get_property_annotations(\ReflectionProperty $property)
    {
        return $this->parser->parse($property->get_doc_comment(), 'property ' . $property->get_declaring_class()->name . '::$' . $property->get_name());
    }
    /**
     * Gets a class annotation.
     *
     * @param ReflectionClass $class          The ReflectionClass of the class from which
     *          the class annotations should be read.
     * @param class-string<T> $annotationName The name of the annotation.
     *
     * @return T|null The Annotation or NULL, if the requested annotation does not exist.
     *
     * @template T
     */
    public function get_class_annotation(\ReflectionClass $class, $annotation_name): ?object
    {
        foreach ($this->get_class_annotations($class) as $annotation) {
            if ($annotation instanceof $annotation_name) {
                return $annotation;
            }
        }
        return null;
    }
    /**
     * Gets a method annotation.
     *
     * @param ReflectionMethod $method         The ReflectionMethod to read the annotations from.
     * @param class-string<T>  $annotationName The name of the annotation.
     *
     * @return T|null The Annotation or NULL, if the requested annotation does not exist.
     *
     * @template T
     */
    public function get_method_annotation(\ReflectionMethod $method, $annotation_name): ?object
    {
        foreach ($this->get_method_annotations($method) as $annotation) {
            if ($annotation instanceof $annotation_name) {
                return $annotation;
            }
        }
        return null;
    }
    /**
     * Gets a property annotation.
     *
     * @param ReflectionProperty $property       The ReflectionProperty to read the annotations from.
     * @param class-string<T>    $annotationName The name of the annotation.
     *
     * @return T|null The Annotation or NULL, if the requested annotation does not exist.
     *
     * @template T
     */
    public function get_property_annotation(\ReflectionProperty $property, $annotation_name): ?object
    {
        foreach ($this->get_property_annotations($property) as $annotation) {
            if ($annotation instanceof $annotation_name) {
                return $annotation;
            }
        }
        return null;
    }
}