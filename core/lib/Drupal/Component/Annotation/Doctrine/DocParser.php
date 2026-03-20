<?php

declare (strict_types=1);
// phpcs:ignoreFile
/**
 * @file
 *
 * This class is a near-copy of Doctrine\Common\Annotations\DocParser, which is
 * part of the Doctrine project: <http://www.doctrine-project.org>. It was
 * copied from version 1.2.7.
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

use Drupal\Component\Annotation\Doctrine\Annotation\Attribute;
use Drupal\Component\Annotation\Doctrine\Annotation\Attributes;
use Drupal\Component\Annotation\Doctrine\Annotation\Enum;
use Drupal\Component\Annotation\Doctrine\Annotation\Target;
/**
 * A parser for docblock annotations.
 *
 * This Drupal version allows for ignoring annotations when namespaces are
 * present.
 *
 * @internal
 */
final class Doc_Parser
{
    /**
     * An array of all valid tokens for a class name.
     */
    private static array $class_identifiers = [Doc_Lexer::T_IDENTIFIER, Doc_Lexer::T_TRUE, Doc_Lexer::T_FALSE, Doc_Lexer::T_NULL];
    /**
     * The lexer.
     *
     * @var \Doctrine\Common\Annotations\DocLexer
     */
    private $lexer;
    /**
     * Current target context.
     *
     * @var string
     */
    private $target;
    /**
     * Doc parser used to collect annotation target.
     */
    private static ?\Drupal\Component\Annotation\Doctrine\Doc_Parser $metadata_parser = null;
    /**
     * Flag to control if the current annotation is nested or not.
     */
    private bool $is_nested_annotation = false;
    /**
     * Hashmap containing all use-statements that are to be used when parsing
     * the given doc block.
     */
    private array $imports = [];
    /**
     * This hashmap is used internally to cache results of class_exists()
     * look-ups.
     */
    private array $class_exists = [];
    /**
     * Whether annotations that have not been imported should be ignored.
     */
    private bool $ignore_not_imported_annotations = false;
    /**
     * An array of default namespaces if operating in simple mode.
     *
     * @var array
     */
    private $namespaces = [];
    /**
     * A list with annotations that are not causing exceptions when not resolved to an annotation class.
     *
     * The names must be the raw names as used in the class, not the fully qualified
     * class names.
     */
    private array $ignored_annotation_names = [];
    /**
     * @var string
     */
    private $context = '';
    /**
     * Hash-map for caching annotation metadata.
     */
    private static array $annotation_metadata = [\Drupal\Component\Annotation\Doctrine\Annotation\Target::class => ['is_annotation' => true, 'has_constructor' => true, 'properties' => [], 'targets_literal' => 'ANNOTATION_CLASS', 'targets' => Target::TARGET_CLASS, 'default_property' => 'value', 'attribute_types' => ['value' => ['required' => false, 'type' => 'array', 'array_type' => 'string', 'value' => 'array<string>']]], \Drupal\Component\Annotation\Doctrine\Annotation\Attribute::class => ['is_annotation' => true, 'has_constructor' => false, 'targets_literal' => 'ANNOTATION_ANNOTATION', 'targets' => Target::TARGET_ANNOTATION, 'default_property' => 'name', 'properties' => ['name' => 'name', 'type' => 'type', 'required' => 'required'], 'attribute_types' => ['value' => ['required' => true, 'type' => 'string', 'value' => 'string'], 'type' => ['required' => true, 'type' => 'string', 'value' => 'string'], 'required' => ['required' => false, 'type' => 'boolean', 'value' => 'boolean']]], \Drupal\Component\Annotation\Doctrine\Annotation\Attributes::class => ['is_annotation' => true, 'has_constructor' => false, 'targets_literal' => 'ANNOTATION_CLASS', 'targets' => Target::TARGET_CLASS, 'default_property' => 'value', 'properties' => ['value' => 'value'], 'attribute_types' => ['value' => ['type' => 'array', 'required' => true, 'array_type' => \Drupal\Component\Annotation\Doctrine\Annotation\Attribute::class, 'value' => 'array<Drupal\Component\Annotation\Doctrine\Annotation\Attribute>']]], \Drupal\Component\Annotation\Doctrine\Annotation\Enum::class => ['is_annotation' => true, 'has_constructor' => true, 'targets_literal' => 'ANNOTATION_PROPERTY', 'targets' => Target::TARGET_PROPERTY, 'default_property' => 'value', 'properties' => ['value' => 'value'], 'attribute_types' => ['value' => ['type' => 'array', 'required' => true], 'literal' => ['type' => 'array', 'required' => false]]]];
    /**
     * Hash-map for handle types declaration.
     */
    private static array $type_map = [
        'float' => 'double',
        'bool' => 'boolean',
        // allow uppercase Boolean in honor of George Boole
        'Boolean' => 'boolean',
        'int' => 'integer',
    ];
    /**
     * Constructs a new DocParser.
     */
    public function __construct()
    {
        $this->lexer = new Doc_Lexer();
    }
    /**
     * Sets the annotation names that are ignored during the parsing process.
     *
     * The names are supposed to be the raw names as used in the class, not the
     * fully qualified class names.
     *
     *
     */
    public function set_ignored_annotation_names(array $names): void
    {
        $this->ignored_annotation_names = $names;
    }
    /**
     * Sets ignore on not-imported annotations.
     *
     * @param boolean $bool
     */
    public function set_ignore_not_imported_annotations($bool): void
    {
        $this->ignore_not_imported_annotations = (bool) $bool;
    }
    /**
     * Sets the default namespaces.
     *
     * @param array $namespace
     *
     *
     * @throws \RuntimeException
     */
    public function add_namespace($namespace): void
    {
        if ($this->imports) {
            throw new \RuntimeException('You must either use addNamespace(), or setImports(), but not both.');
        }
        $this->namespaces[] = $namespace;
    }
    /**
     * Sets the imports.
     *
     *
     *
     * @throws \RuntimeException
     */
    public function set_imports(array $imports): void
    {
        if ($this->namespaces) {
            throw new \RuntimeException('You must either use addNamespace(), or setImports(), but not both.');
        }
        $this->imports = $imports;
    }
    /**
     * Sets current target context as bitmask.
     *
     * @param integer $target
     */
    public function set_target($target): void
    {
        $this->target = $target;
    }
    /**
     * Parses the given docblock string for annotations.
     *
     * @param string $input   The docblock string to parse.
     * @param string $context The parsing context.
     *
     * @return array Array of annotations. If no annotations are found, an empty array is returned.
     */
    public function parse($input, $context = '')
    {
        $pos = $this->find_initial_token_position($input);
        if ($pos === null) {
            return [];
        }
        $this->context = $context;
        $this->lexer->set_input(trim(substr($input, $pos), '* /'));
        $this->lexer->move_next();
        return $this->Annotations();
    }
    /**
     * Finds the first valid annotation
     *
     * @param string $input The docblock string to parse
     */
    private function find_initial_token_position($input): ?int
    {
        $pos = 0;
        // search for first valid annotation
        while (($pos = strpos($input, '@', $pos)) !== false) {
            // if the @ is preceded by a space or * it is valid
            if ($pos === 0 || $input[$pos - 1] === ' ' || $input[$pos - 1] === '*') {
                return $pos;
            }
            $pos++;
        }
        return null;
    }
    /**
     * Attempts to match the given token with the current lookahead token.
     * If they match, updates the lookahead token; otherwise raises a syntax error.
     *
     * @param integer $token Type of token.
     *
     * @return boolean True if tokens match; false otherwise.
     */
    private function match(int $token)
    {
        if (!$this->lexer->is_next_token($token)) {
            $this->syntax_error($this->lexer->get_literal($token));
        }
        return $this->lexer->move_next();
    }
    /**
     * Attempts to match the current lookahead token with any of the given tokens.
     *
     * If any of them matches, this method updates the lookahead token; otherwise
     * a syntax error is raised.
     *
     *
     * @return boolean
     */
    private function match_any(array $tokens)
    {
        if (!$this->lexer->is_next_token_any($tokens)) {
            $this->syntax_error(implode(' or ', array_map([$this->lexer, 'getLiteral'], $tokens)));
        }
        return $this->lexer->move_next();
    }
    /**
     * Generates a new syntax error.
     *
     * @param string     $expected Expected string.
     * @param array|null $token    Optional token.
     *
     *
     * @throws AnnotationException
     */
    private function syntax_error($expected, $token = null): void
    {
        if ($token === null) {
            $token = $this->lexer->lookahead;
        }
        $message = sprintf('Expected %s, got ', $expected);
        $message .= $this->lexer->lookahead === null ? 'end of string' : sprintf("'%s' at position %s", $token->value, $token->position);
        if (strlen($this->context)) {
            $message .= ' in ' . $this->context;
        }
        $message .= '.';
        throw Annotation_Exception::syntax_error($message);
    }
    /**
     * Attempts to check if a class exists or not. This never goes through the PHP autoloading mechanism
     * but uses the {@link AnnotationRegistry} to load classes.
     *
     * @param string $fqcn
     *
     * @return boolean
     */
    private function class_exists($fqcn)
    {
        if (isset($this->class_exists[$fqcn])) {
            return $this->class_exists[$fqcn];
        }
        // first check if the class already exists, maybe loaded through another AnnotationReader
        if (class_exists($fqcn, false)) {
            return $this->class_exists[$fqcn] = true;
        }
        // final check, does this class exist?
        return $this->class_exists[$fqcn] = Annotation_Registry::load_annotation_class($fqcn);
    }
    /**
     * Collects parsing metadata for a given annotation class
     *
     * @param string $name The annotation name
     */
    private function collect_annotation_metadata(string $name): void
    {
        if (self::$metadata_parser === null) {
            self::$metadata_parser = new self();
            self::$metadata_parser->set_ignore_not_imported_annotations(true);
            self::$metadata_parser->set_ignored_annotation_names($this->ignored_annotation_names);
            self::$metadata_parser->set_imports(['enum' => \Drupal\Component\Annotation\Doctrine\Annotation\Enum::class, 'target' => \Drupal\Component\Annotation\Doctrine\Annotation\Target::class, 'attribute' => \Drupal\Component\Annotation\Doctrine\Annotation\Attribute::class, 'attributes' => \Drupal\Component\Annotation\Doctrine\Annotation\Attributes::class]);
        }
        $class = new \ReflectionClass($name);
        $doc_comment = $class->get_doc_comment();
        // Sets default values for annotation metadata
        $metadata = ['default_property' => null, 'has_constructor' => null !== ($constructor = $class->get_constructor()) && $constructor->get_number_of_parameters() > 0, 'properties' => [], 'property_types' => [], 'attribute_types' => [], 'targets_literal' => null, 'targets' => Target::TARGET_ALL, 'is_annotation' => str_contains($doc_comment, '@Annotation')];
        // verify that the class is really meant to be an annotation
        if ($metadata['is_annotation']) {
            self::$metadata_parser->set_target(Target::TARGET_CLASS);
            foreach (self::$metadata_parser->parse($doc_comment, 'class @' . $name) as $annotation) {
                if ($annotation instanceof Target) {
                    $metadata['targets'] = $annotation->targets;
                    $metadata['targets_literal'] = $annotation->literal;
                    continue;
                }
                if ($annotation instanceof Attributes) {
                    foreach ($annotation->value as $attribute) {
                        $this->collect_attribute_type_metadata($metadata, $attribute);
                    }
                }
            }
            // if not has a constructor will inject values into public properties
            if (false === $metadata['has_constructor']) {
                // collect all public properties
                foreach ($class->get_properties(\ReflectionProperty::IS_PUBLIC) as $property) {
                    $metadata['properties'][$property->name] = $property->name;
                    if (false === $property_comment = $property->get_doc_comment()) {
                        continue;
                    }
                    $attribute = new Attribute();
                    $attribute->required = str_contains($property_comment, '@Required');
                    $attribute->name = $property->name;
                    $attribute->type = str_contains($property_comment, '@var') && preg_match('/@var\s+([^\s]+)/', $property_comment, $matches) ? $matches[1] : 'mixed';
                    $this->collect_attribute_type_metadata($metadata, $attribute);
                    // checks if the property has @Enum
                    if (str_contains($property_comment, '@Enum')) {
                        $context = 'property ' . $class->name . '::$' . $property->name;
                        self::$metadata_parser->set_target(Target::TARGET_PROPERTY);
                        foreach (self::$metadata_parser->parse($property_comment, $context) as $annotation) {
                            if (!$annotation instanceof Enum) {
                                continue;
                            }
                            $metadata['enum'][$property->name]['value'] = $annotation->value;
                            $metadata['enum'][$property->name]['literal'] = !empty($annotation->literal) ? $annotation->literal : $annotation->value;
                        }
                    }
                }
                // choose the first property as default property
                $metadata['default_property'] = reset($metadata['properties']);
            }
        }
        self::$annotation_metadata[$name] = $metadata;
    }
    /**
     * Collects parsing metadata for a given attribute.
     *
     *
     */
    private function collect_attribute_type_metadata(array &$metadata, Attribute $attribute): void
    {
        // handle internal type declaration
        $type = self::$type_map[$attribute->type] ?? $attribute->type;
        // handle the case if the property type is mixed
        if ('mixed' === $type) {
            return;
        }
        // Evaluate type
        switch (true) {
            // Checks if the property has array<type>
            case false !== $pos = strpos((string) $type, '<'):
                $array_type = substr((string) $type, $pos + 1, -1);
                $type = 'array';
                if (isset(self::$type_map[$array_type])) {
                    $array_type = self::$type_map[$array_type];
                }
                $metadata['attribute_types'][$attribute->name]['array_type'] = $array_type;
                break;
            // Checks if the property has type[]
            case false !== $pos = strrpos((string) $type, '['):
                $array_type = substr((string) $type, 0, $pos);
                $type = 'array';
                if (isset(self::$type_map[$array_type])) {
                    $array_type = self::$type_map[$array_type];
                }
                $metadata['attribute_types'][$attribute->name]['array_type'] = $array_type;
                break;
        }
        $metadata['attribute_types'][$attribute->name]['type'] = $type;
        $metadata['attribute_types'][$attribute->name]['value'] = $attribute->type;
        $metadata['attribute_types'][$attribute->name]['required'] = $attribute->required;
    }
    /**
     * Annotations ::= Annotation {[ "*" ]* [Annotation]}*
     */
    private function Annotations(): array
    {
        $annotations = [];
        while (null !== $this->lexer->lookahead) {
            if (Doc_Lexer::T_AT !== $this->lexer->lookahead->type) {
                $this->lexer->move_next();
                continue;
            }
            // make sure the @ is preceded by non-catchable pattern
            if (null !== $this->lexer->token && $this->lexer->lookahead->position === $this->lexer->token->position + strlen((string) $this->lexer->token->value)) {
                $this->lexer->move_next();
                continue;
            }
            // make sure the @ is followed by either a namespace separator, or
            // an identifier token
            if (null === ($peek = $this->lexer->glimpse()) || Doc_Lexer::T_NAMESPACE_SEPARATOR !== $peek->type && !in_array($peek->type, self::$class_identifiers, true) || $peek->position !== $this->lexer->lookahead->position + 1) {
                $this->lexer->move_next();
                continue;
            }
            $this->is_nested_annotation = false;
            if (false !== $annotation = $this->Annotation()) {
                $annotations[] = $annotation;
            }
        }
        return $annotations;
    }
    /**
     * Annotation     ::= "@" AnnotationName MethodCall
     * AnnotationName ::= QualifiedName | SimpleName
     * QualifiedName  ::= NameSpacePart "\" {NameSpacePart "\"}* SimpleName
     * NameSpacePart  ::= identifier | null | false | true
     * SimpleName     ::= identifier | null | false | true
     *
     * @return mixed False if it is not a valid annotation.
     *
     * @throws AnnotationException
     */
    private function Annotation(): false|object
    {
        $this->match(Doc_Lexer::T_AT);
        // check if we have an annotation
        $name = $this->Identifier();
        // only process names which are not fully qualified, yet
        // fully qualified names must start with a \
        $original_name = $name;
        if ('\\' !== $name[0]) {
            $alias = false === ($pos = strpos($name, '\\')) ? $name : substr($name, 0, $pos);
            $found = false;
            if ($this->namespaces) {
                if (isset($this->ignored_annotation_names[$name])) {
                    return false;
                }
                foreach ($this->namespaces as $namespace) {
                    if ($this->class_exists($namespace . '\\' . $name)) {
                        $name = $namespace . '\\' . $name;
                        $found = true;
                        break;
                    }
                }
            } elseif (isset($this->imports[$lowered_alias = strtolower($alias)])) {
                $found = true;
                $name = false !== $pos ? $this->imports[$lowered_alias] . substr($name, $pos) : $this->imports[$lowered_alias];
            } elseif (!isset($this->ignored_annotation_names[$name]) && isset($this->imports['__NAMESPACE__']) && $this->class_exists($this->imports['__NAMESPACE__'] . '\\' . $name)) {
                $name = $this->imports['__NAMESPACE__'] . '\\' . $name;
                $found = true;
            } elseif (!isset($this->ignored_annotation_names[$name]) && $this->class_exists($name)) {
                $found = true;
            }
            if (!$found) {
                if ($this->ignore_not_imported_annotations || isset($this->ignored_annotation_names[$name])) {
                    return false;
                }
                throw Annotation_Exception::semantical_error(sprintf('The annotation "@%s" in %s was never imported. Did you maybe forget to add a "use" statement for this annotation?', $name, $this->context));
            }
        }
        if (!$this->class_exists($name)) {
            throw Annotation_Exception::semantical_error(sprintf('The annotation "@%s" in %s does not exist, or could not be auto-loaded.', $name, $this->context));
        }
        // at this point, $name contains the fully qualified class name of the
        // annotation, and it is also guaranteed that this class exists, and
        // that it is loaded
        // collects the metadata annotation only if there is not yet
        if (!isset(self::$annotation_metadata[$name])) {
            $this->collect_annotation_metadata($name);
        }
        // verify that the class is really meant to be an annotation and not just any ordinary class
        if (self::$annotation_metadata[$name]['is_annotation'] === false) {
            if (isset($this->ignored_annotation_names[$original_name])) {
                return false;
            }
            throw Annotation_Exception::semantical_error(sprintf('The class "%s" is not annotated with @Annotation. Are you sure this class can be used as annotation? If so, then you need to add @Annotation to the _class_ doc comment of "%s". If it is indeed no annotation, then you need to add @IgnoreAnnotation("%s") to the _class_ doc comment of %s.', $name, $name, $original_name, $this->context));
        }
        //if target is nested annotation
        $target = $this->is_nested_annotation ? Target::TARGET_ANNOTATION : $this->target;
        // Next will be nested
        $this->is_nested_annotation = true;
        //if annotation does not support current target
        if (0 === (self::$annotation_metadata[$name]['targets'] & $target) && $target) {
            throw Annotation_Exception::semantical_error(sprintf('Annotation @%s is not allowed to be declared on %s. You may only use this annotation on these code elements: %s.', $original_name, $this->context, self::$annotation_metadata[$name]['targets_literal']));
        }
        $values = $this->method_call();
        if (isset(self::$annotation_metadata[$name]['enum'])) {
            // checks all declared attributes
            foreach (self::$annotation_metadata[$name]['enum'] as $property => $enum) {
                // checks if the attribute is a valid enumerator
                if (isset($values[$property]) && !in_array($values[$property], $enum['value'])) {
                    throw Annotation_Exception::enumerator_error($property, $name, $this->context, $enum['literal'], $values[$property]);
                }
            }
        }
        // checks all declared attributes
        foreach (self::$annotation_metadata[$name]['attribute_types'] as $property => $type) {
            if ($property === self::$annotation_metadata[$name]['default_property'] && !isset($values[$property]) && isset($values['value'])) {
                $property = 'value';
            }
            // handle a not given attribute or null value
            if (!isset($values[$property])) {
                if ($type['required']) {
                    throw Annotation_Exception::required_error($property, $original_name, $this->context, 'a(n) ' . $type['value']);
                }
                continue;
            }
            if ($type['type'] === 'array') {
                // handle the case of a single value
                if (!is_array($values[$property])) {
                    $values[$property] = [$values[$property]];
                }
                // checks if the attribute has array type declaration, such as "array<string>"
                if (isset($type['array_type'])) {
                    foreach ($values[$property] as $item) {
                        if (gettype($item) !== $type['array_type'] && !$item instanceof $type['array_type']) {
                            throw Annotation_Exception::attribute_type_error($property, $original_name, $this->context, 'either a(n) ' . $type['array_type'] . ', or an array of ' . $type['array_type'] . 's', $item);
                        }
                    }
                }
            } elseif (gettype($values[$property]) !== $type['type'] && !$values[$property] instanceof $type['type']) {
                throw Annotation_Exception::attribute_type_error($property, $original_name, $this->context, 'a(n) ' . $type['value'], $values[$property]);
            }
        }
        // check if the annotation expects values via the constructor,
        // or directly injected into public properties
        if (self::$annotation_metadata[$name]['has_constructor'] === true) {
            return new $name($values);
        }
        $instance = new $name();
        foreach ($values as $property => $value) {
            if (!isset(self::$annotation_metadata[$name]['properties'][$property])) {
                if ('value' !== $property) {
                    throw Annotation_Exception::creation_error(sprintf('The annotation @%s declared on %s does not have a property named "%s". Available properties: %s', $original_name, $this->context, $property, implode(', ', self::$annotation_metadata[$name]['properties'])));
                }
                // handle the case if the property has no annotations
                if (!$property = self::$annotation_metadata[$name]['default_property']) {
                    throw Annotation_Exception::creation_error(sprintf('The annotation @%s declared on %s does not accept any values, but got %s.', $original_name, $this->context, json_encode($values)));
                }
            }
            $instance->{$property} = $value;
        }
        return $instance;
    }
    /**
     * MethodCall ::= ["(" [Values] ")"]
     *
     * @return array
     */
    private function method_call()
    {
        $values = [];
        if (!$this->lexer->is_next_token(Doc_Lexer::T_OPEN_PARENTHESIS)) {
            return $values;
        }
        $this->match(Doc_Lexer::T_OPEN_PARENTHESIS);
        if (!$this->lexer->is_next_token(Doc_Lexer::T_CLOSE_PARENTHESIS)) {
            $values = $this->Values();
        }
        $this->match(Doc_Lexer::T_CLOSE_PARENTHESIS);
        return $values;
    }
    /**
     * Values ::= Array | Value {"," Value}* [","]
     */
    private function Values(): array
    {
        $values = [$this->Value()];
        while ($this->lexer->is_next_token(Doc_Lexer::T_COMMA)) {
            $this->match(Doc_Lexer::T_COMMA);
            if ($this->lexer->is_next_token(Doc_Lexer::T_CLOSE_PARENTHESIS)) {
                break;
            }
            $token = $this->lexer->lookahead;
            $value = $this->Value();
            if (!is_object($value) && !is_array($value)) {
                $this->syntax_error('Value', $token);
            }
            $values[] = $value;
        }
        foreach ($values as $k => $value) {
            if (is_object($value) && $value instanceof \stdClass) {
                $values[$value->name] = $value->value;
            } elseif (!isset($values['value'])) {
                $values['value'] = $value;
            } else {
                if (!is_array($values['value'])) {
                    $values['value'] = [$values['value']];
                }
                $values['value'][] = $value;
            }
            unset($values[$k]);
        }
        return $values;
    }
    /**
     * Constant ::= integer | string | float | boolean
     *
     * @return mixed
     *
     * @throws AnnotationException
     */
    private function Constant()
    {
        $identifier = $this->Identifier();
        if (!defined($identifier) && str_contains($identifier, '::') && '\\' !== $identifier[0]) {
            [$class_name, $const] = explode('::', $identifier);
            $alias = false === ($pos = strpos($class_name, '\\')) ? $class_name : substr($class_name, 0, $pos);
            $found = false;
            switch (true) {
                case !empty($this->namespaces):
                    foreach ($this->namespaces as $ns) {
                        if (class_exists($ns . '\\' . $class_name) || interface_exists($ns . '\\' . $class_name)) {
                            $class_name = $ns . '\\' . $class_name;
                            $found = true;
                            break;
                        }
                    }
                    break;
                case isset($this->imports[$lowered_alias = strtolower($alias)]):
                    $found = true;
                    $class_name = false !== $pos ? $this->imports[$lowered_alias] . substr($class_name, $pos) : $this->imports[$lowered_alias];
                    break;
                default:
                    if (isset($this->imports['__NAMESPACE__'])) {
                        $ns = $this->imports['__NAMESPACE__'];
                        if (class_exists($ns . '\\' . $class_name) || interface_exists($ns . '\\' . $class_name)) {
                            $class_name = $ns . '\\' . $class_name;
                            $found = true;
                        }
                    }
                    break;
            }
            if ($found) {
                $identifier = $class_name . '::' . $const;
            }
        }
        /**
         * Checks if identifier ends with ::class and remove the leading backslash if it exists.
         */
        if ($this->identifier_ends_with_class_constant($identifier) && !$this->identifier_starts_with_backslash($identifier)) {
            return substr($identifier, 0, $this->get_class_constant_position_in_identifier($identifier));
        }
        if ($this->identifier_ends_with_class_constant($identifier) && $this->identifier_starts_with_backslash($identifier)) {
            return substr($identifier, 1, $this->get_class_constant_position_in_identifier($identifier) - 1);
        }
        if (!defined($identifier)) {
            throw Annotation_Exception::semantical_error_constants($identifier, $this->context);
        }
        return constant($identifier);
    }
    private function identifier_starts_with_backslash(string $identifier): bool
    {
        return '\\' === $identifier[0];
    }
    private function identifier_ends_with_class_constant(string $identifier): bool
    {
        return $this->get_class_constant_position_in_identifier($identifier) === strlen($identifier) - strlen('::class');
    }
    /**
     * @return int|false
     */
    private function get_class_constant_position_in_identifier(string $identifier): int|false
    {
        return stripos($identifier, '::class');
    }
    /**
     * Identifier ::= string
     *
     * @return string
     */
    private function Identifier()
    {
        // check if we have an annotation
        if (!$this->lexer->is_next_token_any(self::$class_identifiers)) {
            $this->syntax_error('namespace separator or identifier');
        }
        $this->lexer->move_next();
        $class_name = $this->lexer->token->value;
        while (null !== $this->lexer->lookahead && $this->lexer->lookahead->position === $this->lexer->token->position + strlen((string) $this->lexer->token->value) && $this->lexer->is_next_token(Doc_Lexer::T_NAMESPACE_SEPARATOR)) {
            $this->match(Doc_Lexer::T_NAMESPACE_SEPARATOR);
            $this->match_any(self::$class_identifiers);
            $class_name .= '\\' . $this->lexer->token->value;
        }
        return $class_name;
    }
    /**
     * Value ::= PlainValue | FieldAssignment
     *
     * @return mixed
     */
    private function Value()
    {
        $peek = $this->lexer->glimpse();
        if (Doc_Lexer::T_EQUALS === $peek->type) {
            return $this->field_assignment();
        }
        return $this->plain_value();
    }
    /**
     * PlainValue ::= integer | string | float | boolean | Array | Annotation
     *
     * @return mixed
     */
    private function plain_value()
    {
        if ($this->lexer->is_next_token(Doc_Lexer::T_OPEN_CURLY_BRACES)) {
            return $this->array_x();
        }
        if ($this->lexer->is_next_token(Doc_Lexer::T_AT)) {
            return $this->Annotation();
        }
        if ($this->lexer->is_next_token(Doc_Lexer::T_IDENTIFIER)) {
            return $this->Constant();
        }
        switch ($this->lexer->lookahead->type) {
            case Doc_Lexer::T_STRING:
                $this->match(Doc_Lexer::T_STRING);
                return $this->lexer->token->value;
            case Doc_Lexer::T_INTEGER:
                $this->match(Doc_Lexer::T_INTEGER);
                return (int) $this->lexer->token->value;
            case Doc_Lexer::T_FLOAT:
                $this->match(Doc_Lexer::T_FLOAT);
                return (float) $this->lexer->token->value;
            case Doc_Lexer::T_TRUE:
                $this->match(Doc_Lexer::T_TRUE);
                return true;
            case Doc_Lexer::T_FALSE:
                $this->match(Doc_Lexer::T_FALSE);
                return false;
            case Doc_Lexer::T_NULL:
                $this->match(Doc_Lexer::T_NULL);
                return null;
            default:
                $this->syntax_error('PlainValue');
        }
    }
    /**
     * FieldAssignment ::= FieldName "=" PlainValue
     * FieldName ::= identifier
     *
     * @return array
     */
    private function field_assignment(): \stdClass
    {
        $this->match(Doc_Lexer::T_IDENTIFIER);
        $field_name = $this->lexer->token->value;
        $this->match(Doc_Lexer::T_EQUALS);
        $item = new \stdClass();
        $item->name = $field_name;
        $item->value = $this->plain_value();
        return $item;
    }
    /**
     * Array ::= "{" ArrayEntry {"," ArrayEntry}* [","] "}"
     */
    private function array_x(): array
    {
        $array = $values = [];
        $this->match(Doc_Lexer::T_OPEN_CURLY_BRACES);
        // If the array is empty, stop parsing and return.
        if ($this->lexer->is_next_token(Doc_Lexer::T_CLOSE_CURLY_BRACES)) {
            $this->match(Doc_Lexer::T_CLOSE_CURLY_BRACES);
            return $array;
        }
        $values[] = $this->array_entry();
        while ($this->lexer->is_next_token(Doc_Lexer::T_COMMA)) {
            $this->match(Doc_Lexer::T_COMMA);
            // optional trailing comma
            if ($this->lexer->is_next_token(Doc_Lexer::T_CLOSE_CURLY_BRACES)) {
                break;
            }
            $values[] = $this->array_entry();
        }
        $this->match(Doc_Lexer::T_CLOSE_CURLY_BRACES);
        foreach ($values as $value) {
            [$key, $val] = $value;
            if ($key !== null) {
                $array[$key] = $val;
            } else {
                $array[] = $val;
            }
        }
        return $array;
    }
    /**
     * ArrayEntry ::= Value | KeyValuePair
     * KeyValuePair ::= Key ("=" | ":") PlainValue | Constant
     * Key ::= string | integer | Constant
     */
    private function array_entry(): array
    {
        $peek = $this->lexer->glimpse();
        if (Doc_Lexer::T_EQUALS === $peek->type || Doc_Lexer::T_COLON === $peek->type) {
            if ($this->lexer->is_next_token(Doc_Lexer::T_IDENTIFIER)) {
                $key = $this->Constant();
            } else {
                $this->match_any([Doc_Lexer::T_INTEGER, Doc_Lexer::T_STRING]);
                $key = $this->lexer->token->value;
            }
            $this->match_any([Doc_Lexer::T_EQUALS, Doc_Lexer::T_COLON]);
            return [$key, $this->plain_value()];
        }
        return [null, $this->Value()];
    }
}