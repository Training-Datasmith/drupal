<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Annotation\Doctrine;

use Drupal\Component\Annotation\Doctrine\Annotation\Target;
use Drupal\Component\Annotation\Doctrine\DocParser;
use Drupal\Tests\Component\Annotation\Doctrine\Fixtures\Annotation\Autoload;
use Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants;
use Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithConstants;
use Drupal\Tests\Component\Annotation\Doctrine\Fixtures\IntefaceWithConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * This class is a near-copy of
 * Doctrine\Tests\Common\Annotations\DocParserTest, which is part of the
 * Doctrine project: <http://www.doctrine-project.org>.  It was copied from
 * version 1.2.7.
 *
 * The supporting test fixture classes in
 * core/tests/Drupal/Tests/Component/Annotation/Doctrine/Fixtures were also
 * copied from version 1.2.7.
 */
#[CoversClass(DocParser::class)]
#[Group('Annotation')]
class DocParserTest extends TestCase
{
    public function testNestedArraysWithNestedAnnotation(): void
    {
        $parser = $this->createTestParser();

        // Nested arrays with nested annotations
        $result = $parser->parse('@Name(foo={1,2, {"key"=@Name}})');
        $annot = $result[0];

        $this->assertInstanceOf(Name::class, $annot);
        $this->assertNull($annot->value);
        $this->assertCount(3, $annot->foo);
        $this->assertEquals(1, $annot->foo[0]);
        $this->assertEquals(2, $annot->foo[1]);
        $this->assertIsArray($annot->foo[2]);

        $nestedArray = $annot->foo[2];
        $this->assertTrue(isset($nestedArray['key']));
        $this->assertInstanceOf(Name::class, $nestedArray['key']);
    }

    public function testBasicAnnotations(): void
    {
        $parser = $this->createTestParser();

        // Marker annotation
        $result = $parser->parse('@Name');
        $annot = $result[0];
        $this->assertInstanceOf(Name::class, $annot);
        $this->assertNull($annot->value);
        $this->assertNull($annot->foo);

        // Associative arrays
        $result = $parser->parse('@Name(foo={"key1" = "value1"})');
        $annot = $result[0];
        $this->assertNull($annot->value);
        $this->assertIsArray($annot->foo);
        $this->assertTrue(isset($annot->foo['key1']));

        // Numerical arrays
        $result = $parser->parse('@Name({2="foo", 4="bar"})');
        $annot = $result[0];
        $this->assertIsArray($annot->value);
        $this->assertEquals('foo', $annot->value[2]);
        $this->assertEquals('bar', $annot->value[4]);
        $this->assertFalse(isset($annot->value[0]));
        $this->assertFalse(isset($annot->value[1]));
        $this->assertFalse(isset($annot->value[3]));

        // Multiple values
        $result = $parser->parse('@Name(@Name, @Name)');
        $annot = $result[0];

        $this->assertInstanceOf(Name::class, $annot);
        $this->assertIsArray($annot->value);
        $this->assertInstanceOf(Name::class, $annot->value[0]);
        $this->assertInstanceOf(Name::class, $annot->value[1]);

        // Multiple types as values
        $result = $parser->parse('@Name(foo="Bar", @Name, {"key1"="value1", "key2"="value2"})');
        $annot = $result[0];

        $this->assertInstanceOf(Name::class, $annot);
        $this->assertIsArray($annot->value);
        $this->assertInstanceOf(Name::class, $annot->value[0]);
        $this->assertIsArray($annot->value[1]);
        $this->assertEquals('value1', $annot->value[1]['key1']);
        $this->assertEquals('value2', $annot->value[1]['key2']);

        // Complete docblock
        $docblock = <<<DOCBLOCK
/**
 * Some nifty class.
 *
 * @author Mr.X
 * @Name(foo="bar")
 */
DOCBLOCK;

        $result = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot = $result[0];
        $this->assertInstanceOf(Name::class, $annot);
        $this->assertEquals('bar', $annot->foo);
        $this->assertNull($annot->value);
    }

    public function testDefaultValueAnnotations(): void
    {
        $parser = $this->createTestParser();

        // Array as first value
        $result = $parser->parse('@Name({"key1"="value1"})');
        $annot = $result[0];

        $this->assertInstanceOf(Name::class, $annot);
        $this->assertIsArray($annot->value);
        $this->assertEquals('value1', $annot->value['key1']);

        // Array as first value and additional values
        $result = $parser->parse('@Name({"key1"="value1"}, foo="bar")');
        $annot = $result[0];

        $this->assertInstanceOf(Name::class, $annot);
        $this->assertIsArray($annot->value);
        $this->assertEquals('value1', $annot->value['key1']);
        $this->assertEquals('bar', $annot->foo);
    }

    public function testNamespacedAnnotations(): void
    {
        $parser = new DocParser();
        $parser->setIgnoreNotImportedAnnotations(true);

        $docblock = <<<DOCBLOCK
/**
 * Some nifty class.
 *
 * @package foo
 * @subpackage bar
 * @author Mr.X <mr@x.com>
 * @Drupal\Tests\Component\Annotation\Doctrine\Name(foo="bar")
 * @ignore
 */
DOCBLOCK;

        $result = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot = $result[0];
        $this->assertInstanceOf(Name::class, $annot);
        $this->assertEquals('bar', $annot->foo);
    }

    /**
     * Tests typical method doc block.
     */
    #[Group('debug')]
    public function testTypicalMethodDocBlock(): void
    {
        $parser = $this->createTestParser();

        $docblock = <<<DOCBLOCK
/**
 * Some nifty method.
 *
 * @since 2.0
 * @Drupal\Tests\Component\Annotation\Doctrine\Name(foo="bar")
 * @param string \$foo This is foo.
 * @param mixed \$bar This is bar.
 * @return string Foo and bar.
 * @This is irrelevant
 * @Marker
 */
DOCBLOCK;

        $result = $parser->parse($docblock);
        $this->assertCount(2, $result);
        $this->assertTrue(isset($result[0]));
        $this->assertTrue(isset($result[1]));
        $annot = $result[0];
        $this->assertInstanceOf(Name::class, $annot);
        $this->assertEquals('bar', $annot->foo);
        $marker = $result[1];
        $this->assertInstanceOf(Marker::class, $marker);
    }

    public function testAnnotationWithoutConstructor(): void
    {
        $parser = $this->createTestParser();

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor("Some data")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertNotNull($annot);
        $this->assertInstanceOf(SomeAnnotationClassNameWithoutConstructor::class, $annot);

        $this->assertNull($annot->name);
        $this->assertNotNull($annot->data);
        $this->assertEquals('Some data', $annot->data);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor(name="Some Name", data = "Some data")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertNotNull($annot);
        $this->assertInstanceOf(SomeAnnotationClassNameWithoutConstructor::class, $annot);

        $this->assertEquals('Some Name', $annot->name);
        $this->assertEquals('Some data', $annot->data);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor(data = "Some data")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertEquals('Some data', $annot->data);
        $this->assertNull($annot->name);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor(name = "Some name")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertEquals('Some name', $annot->name);
        $this->assertNull($annot->data);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor("Some data")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertEquals('Some data', $annot->data);
        $this->assertNull($annot->name);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor("Some data",name = "Some name")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertEquals('Some name', $annot->name);
        $this->assertEquals('Some data', $annot->data);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationWithConstructorWithoutParams(name = "Some name")
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $annot      = $result[0];

        $this->assertEquals('Some name', $annot->name);
        $this->assertEquals('Some data', $annot->data);

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructorAndProperties()
 */
DOCBLOCK;

        $result     = $parser->parse($docblock);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(SomeAnnotationClassNameWithoutConstructorAndProperties::class, $result[0]);
    }

    public function testAnnotationTarget(): void
    {

        $parser = new DocParser();
        $parser->setImports([
            '__NAMESPACE__' => 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures',
        ]);
        $class  = new \ReflectionClass('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithValidAnnotationTarget');

        $context    = 'class ' . $class->getName();
        $docComment = $class->getDocComment();

        $parser->setTarget(Target::TARGET_CLASS);
        $this->assertNotNull($parser->parse($docComment, $context));

        $property   = $class->getProperty('foo');
        $docComment = $property->getDocComment();
        $context    = 'property ' . $class->getName() . '::$' . $property->getName();

        $parser->setTarget(Target::TARGET_PROPERTY);
        $this->assertNotNull($parser->parse($docComment, $context));

        $method     = $class->getMethod('someFunction');
        $docComment = $property->getDocComment();
        $context    = 'method ' . $class->getName() . '::' . $method->getName() . '()';

        $parser->setTarget(Target::TARGET_METHOD);
        $this->assertNotNull($parser->parse($docComment, $context));

        try {
            $class      = new \ReflectionClass('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithInvalidAnnotationTargetAtClass');
            $context    = 'class ' . $class->getName();
            $docComment = $class->getDocComment();

            $parser->setTarget(Target::TARGET_CLASS);
            $parser->parse($docComment, $context);

            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertNotNull($exc->getMessage());
        }

        try {

            $class      = new \ReflectionClass('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithInvalidAnnotationTargetAtMethod');
            $method     = $class->getMethod('functionName');
            $docComment = $method->getDocComment();
            $context    = 'method ' . $class->getName() . '::' . $method->getName() . '()';

            $parser->setTarget(Target::TARGET_METHOD);
            $parser->parse($docComment, $context);

            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertNotNull($exc->getMessage());
        }

        try {
            $class      = new \ReflectionClass('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithInvalidAnnotationTargetAtProperty');
            $property   = $class->getProperty('foo');
            $docComment = $property->getDocComment();
            $context    = 'property ' . $class->getName() . '::$' . $property->getName();

            $parser->setTarget(Target::TARGET_PROPERTY);
            $parser->parse($docComment, $context);

            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertNotNull($exc->getMessage());
        }

    }

    /**
     * @phpstan-ignore missingType.return
     */
    public static function getAnnotationVarTypeProviderValid()
    {
        //({attribute name}, {attribute value})
        return [
           // mixed type
           ['mixed', '"String Value"'],
           ['mixed', 'true'],
           ['mixed', 'false'],
           ['mixed', '1'],
           ['mixed', '1.2'],
           ['mixed', '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll'],

           // boolean type
           ['boolean', 'true'],
           ['boolean', 'false'],

           // alias for internal type boolean
           ['bool', 'true'],
           ['bool', 'false'],

           // integer type
           ['integer', '0'],
           ['integer', '1'],
           ['integer', '123456789'],
           ['integer', '9223372036854775807'],

           // alias for internal type double
           ['float', '0.1'],
           ['float', '1.2'],
           ['float', '123.456'],

           // string type
           ['string', '"String Value"'],
           ['string', '"true"'],
           ['string', '"123"'],

             // array type
           ['array', '{@AnnotationExtendsAnnotationTargetAll}'],
           ['array', '{@AnnotationExtendsAnnotationTargetAll,@AnnotationExtendsAnnotationTargetAll}'],

           ['arrayOfIntegers', '1'],
           ['arrayOfIntegers', '{1}'],
           ['arrayOfIntegers', '{1,2,3,4}'],
           ['arrayOfAnnotations', '@AnnotationExtendsAnnotationTargetAll'],
           ['arrayOfAnnotations', '{@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll}'],
           ['arrayOfAnnotations', '{@AnnotationExtendsAnnotationTargetAll, @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll}'],

           // annotation instance
           ['annotation', '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll'],
           ['annotation', '@AnnotationExtendsAnnotationTargetAll'],
        ];
    }

    /**
     * @phpstan-ignore missingType.return
     */
    public static function getAnnotationVarTypeProviderInvalid()
    {
        //({attribute name}, {type declared type}, {attribute value} , {given type or class})
        return [
           // boolean type
           ['boolean','boolean','1','integer'],
           ['boolean','boolean','1.2','double'],
           ['boolean','boolean','"str"','string'],
           ['boolean','boolean','{1,2,3}','array'],
           ['boolean','boolean','@Name', 'an instance of Drupal\Tests\Component\Annotation\Doctrine\Name'],

           // alias for internal type boolean
           ['bool','bool', '1','integer'],
           ['bool','bool', '1.2','double'],
           ['bool','bool', '"str"','string'],
           ['bool','bool', '{"str"}','array'],

           // integer type
           ['integer','integer', 'true','boolean'],
           ['integer','integer', 'false','boolean'],
           ['integer','integer', '1.2','double'],
           ['integer','integer', '"str"','string'],
           ['integer','integer', '{"str"}','array'],
           ['integer','integer', '{1,2,3,4}','array'],

           // alias for internal type double
           ['float','float', 'true','boolean'],
           ['float','float', 'false','boolean'],
           ['float','float', '123','integer'],
           ['float','float', '"str"','string'],
           ['float','float', '{"str"}','array'],
           ['float','float', '{12.34}','array'],
           ['float','float', '{1,2,3}','array'],

           // string type
           ['string','string', 'true','boolean'],
           ['string','string', 'false','boolean'],
           ['string','string', '12','integer'],
           ['string','string', '1.2','double'],
           ['string','string', '{"str"}','array'],
           ['string','string', '{1,2,3,4}','array'],

            // annotation instance
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', 'true','boolean'],
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', 'false','boolean'],
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '12','integer'],
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '1.2','double'],
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '{"str"}','array'],
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '{1,2,3,4}','array'],
           ['annotation','Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '@Name','an instance of Drupal\Tests\Component\Annotation\Doctrine\Name'],
        ];
    }

    /**
     * @phpstan-ignore missingType.return
     */
    public static function getAnnotationVarTypeArrayProviderInvalid()
    {
        //({attribute name}, {type declared type}, {attribute value} , {given type or class})
        return [
           ['arrayOfIntegers', 'integer', 'true', 'boolean'],
           ['arrayOfIntegers', 'integer', 'false', 'boolean'],
           ['arrayOfIntegers', 'integer', '{true,true}', 'boolean'],
           ['arrayOfIntegers', 'integer', '{1,true}', 'boolean'],
           ['arrayOfIntegers', 'integer', '{1,2,1.2}', 'double'],
           ['arrayOfIntegers', 'integer', '{1,2,"str"}', 'string'],

           ['arrayOfStrings', 'string', 'true', 'boolean'],
           ['arrayOfStrings', 'string', 'false', 'boolean'],
           ['arrayOfStrings', 'string', '{true,true}', 'boolean'],
           ['arrayOfStrings', 'string', '{"foo",true}', 'boolean'],
           ['arrayOfStrings', 'string', '{"foo","bar",1.2}', 'double'],
           ['arrayOfStrings', 'string', '1', 'integer'],

           ['arrayOfAnnotations', 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', 'true', 'boolean'],
           ['arrayOfAnnotations', 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', 'false', 'boolean'],
           ['arrayOfAnnotations', 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '{@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll,true}', 'boolean'],
           ['arrayOfAnnotations', 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '{@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll,true}', 'boolean'],
           ['arrayOfAnnotations', 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '{@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll,1.2}', 'double'],
           ['arrayOfAnnotations', 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll', '{@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll,@AnnotationExtendsAnnotationTargetAll,"str"}', 'string'],
        ];
    }

    /**
     * Tests annotation with var type.
     */
    #[DataProvider('getAnnotationVarTypeProviderValid')]
    public function testAnnotationWithVarType($attribute, $value): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::$invalidProperty.';
        $docblock   = sprintf('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithVarType(%s = %s)', $attribute, $value);
        $parser->setTarget(Target::TARGET_PROPERTY);

        $result = $parser->parse($docblock, $context);

        $this->assertCount(1, $result);
        $this->assertInstanceOf('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithVarType', $result[0]);
        $this->assertNotNull($result[0]->$attribute);
    }

    /**
     * Tests annotation with var type error.
     */
    #[DataProvider('getAnnotationVarTypeProviderInvalid')]
    public function testAnnotationWithVarTypeError($attribute, $type, $value, $given): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $docblock   = sprintf('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithVarType(%s = %s)', $attribute, $value);
        $parser->setTarget(Target::TARGET_PROPERTY);

        try {
            $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString("[Type Error] Attribute \"$attribute\" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithVarType declared on property SomeClassName::invalidProperty. expects a(n) $type, but got $given.", $exc->getMessage());
        }
    }

    /**
     * Tests annotation with var type array error.
     */
    #[DataProvider('getAnnotationVarTypeArrayProviderInvalid')]
    public function testAnnotationWithVarTypeArrayError($attribute, $type, $value, $given): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $docblock   = sprintf('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithVarType(%s = %s)', $attribute, $value);
        $parser->setTarget(Target::TARGET_PROPERTY);

        try {
            $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString("[Type Error] Attribute \"$attribute\" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithVarType declared on property SomeClassName::invalidProperty. expects either a(n) $type, or an array of {$type}s, but got $given.", $exc->getMessage());
        }
    }

    /**
     * Tests annotation with attributes.
     */
    #[DataProvider('getAnnotationVarTypeProviderValid')]
    public function testAnnotationWithAttributes($attribute, $value): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::$invalidProperty.';
        $docblock   = sprintf('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithAttributes(%s = %s)', $attribute, $value);
        $parser->setTarget(Target::TARGET_PROPERTY);

        $result = $parser->parse($docblock, $context);

        $this->assertCount(1, $result);
        $this->assertInstanceOf('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithAttributes', $result[0]);
        $getter = 'get'.ucfirst($attribute);
        $this->assertNotNull($result[0]->$getter());
    }

    /**
     * Tests annotation with attributes error.
     */
    #[DataProvider('getAnnotationVarTypeProviderInvalid')]
    public function testAnnotationWithAttributesError($attribute, $type, $value, $given): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $docblock   = sprintf('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithAttributes(%s = %s)', $attribute, $value);
        $parser->setTarget(Target::TARGET_PROPERTY);

        try {
            $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString("[Type Error] Attribute \"$attribute\" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithAttributes declared on property SomeClassName::invalidProperty. expects a(n) $type, but got $given.", $exc->getMessage());
        }
    }

    /**
     * Tests annotation with attributes with var type array error.
     */
    #[DataProvider('getAnnotationVarTypeArrayProviderInvalid')]
    public function testAnnotationWithAttributesWithVarTypeArrayError($attribute, $type, $value, $given): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $docblock   = sprintf('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithAttributes(%s = %s)', $attribute, $value);
        $parser->setTarget(Target::TARGET_PROPERTY);

        try {
            $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString("[Type Error] Attribute \"$attribute\" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithAttributes declared on property SomeClassName::invalidProperty. expects either a(n) $type, or an array of {$type}s, but got $given.", $exc->getMessage());
        }
    }

    public function testAnnotationWithRequiredAttributes(): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $parser->setTarget(Target::TARGET_PROPERTY);

        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributes("Some Value", annot = @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation)';
        $result     = $parser->parse($docblock);

        $this->assertCount(1, $result);
        $this->assertInstanceOf('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributes', $result[0]);
        $this->assertEquals('Some Value', $result[0]->getValue());
        $this->assertInstanceOf('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation', $result[0]->getAnnot());

        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributes("Some Value")';
        try {
            $result = $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString('Attribute "annot" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributes declared on property SomeClassName::invalidProperty. expects a(n) Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation. This value should not be null.', $exc->getMessage());
        }

        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributes(annot = @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation)';
        try {
            $result = $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString('Attribute "value" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributes declared on property SomeClassName::invalidProperty. expects a(n) string. This value should not be null.', $exc->getMessage());
        }

    }

    public function testAnnotationWithRequiredAttributesWithoutContructor(): void
    {
        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $parser->setTarget(Target::TARGET_PROPERTY);

        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributesWithoutContructor("Some Value", annot = @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation)';
        $result     = $parser->parse($docblock);

        $this->assertCount(1, $result);
        $this->assertInstanceOf('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributesWithoutContructor', $result[0]);
        $this->assertEquals('Some Value', $result[0]->value);
        $this->assertInstanceOf('Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation', $result[0]->annot);

        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributesWithoutContructor("Some Value")';
        try {
            $result = $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString('Attribute "annot" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributesWithoutContructor declared on property SomeClassName::invalidProperty. expects a(n) Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation. This value should not be null.', $exc->getMessage());
        }

        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributesWithoutContructor(annot = @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAnnotation)';
        try {
            $result = $parser->parse($docblock, $context);
            $this->fail();
        } catch (\Drupal\Component\Annotation\Doctrine\AnnotationException $exc) {
            $this->assertStringContainsString('Attribute "value" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithRequiredAttributesWithoutContructor declared on property SomeClassName::invalidProperty. expects a(n) string. This value should not be null.', $exc->getMessage());
        }

    }

    public function testAnnotationEnumeratorException(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('Attribute "value" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationEnum declared on property SomeClassName::invalidProperty. accepts only [ONE, TWO, THREE], but got FOUR.');

        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationEnum("FOUR")';

        $parser->setIgnoreNotImportedAnnotations(false);
        $parser->setTarget(Target::TARGET_PROPERTY);
        $parser->parse($docblock, $context);
    }

    public function testAnnotationEnumeratorLiteralException(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('Attribute "value" of @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationEnumLiteral declared on property SomeClassName::invalidProperty. accepts only [AnnotationEnumLiteral::ONE, AnnotationEnumLiteral::TWO, AnnotationEnumLiteral::THREE], but got 4.');

        $parser     = $this->createTestParser();
        $context    = 'property SomeClassName::invalidProperty.';
        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationEnumLiteral(4)';

        $parser->setIgnoreNotImportedAnnotations(false);
        $parser->setTarget(Target::TARGET_PROPERTY);
        $parser->parse($docblock, $context);
    }

    public function testAnnotationEnumInvalidTypeDeclarationException(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->expectExceptionMessage('@Enum supports only scalar values "array" given.');

        $parser     = $this->createTestParser();
        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationEnumInvalid("foo")';

        $parser->setIgnoreNotImportedAnnotations(false);
        $parser->parse($docblock);
    }

    public function testAnnotationEnumInvalidLiteralDeclarationException(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->expectExceptionMessage('Undefined enumerator value "3" for literal "AnnotationEnumLiteral::THREE".');

        $parser     = $this->createTestParser();
        $docblock   = '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationEnumLiteralInvalid("foo")';

        $parser->setIgnoreNotImportedAnnotations(false);
        $parser->parse($docblock);
    }

    /**
     * @phpstan-ignore missingType.return
     */
    public static function getConstantsProvider()
    {
        $provider[] = [
            '@AnnotationWithConstants(PHP_EOL)',
            PHP_EOL,
        ];
        $provider[] = [
            '@AnnotationWithConstants(\SimpleXMLElement::class)',
            \SimpleXMLElement::class,
        ];
        $provider[] = [
            '@AnnotationWithConstants(AnnotationWithConstants::INTEGER)',
            AnnotationWithConstants::INTEGER,
        ];
        $provider[] = [
            '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants(AnnotationWithConstants::STRING)',
            AnnotationWithConstants::STRING,
        ];
        $provider[] = [
            '@AnnotationWithConstants(Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants::FLOAT)',
            AnnotationWithConstants::FLOAT,
        ];
        $provider[] = [
            '@AnnotationWithConstants(ClassWithConstants::SOME_VALUE)',
            ClassWithConstants::SOME_VALUE,
        ];
        $provider[] = [
            '@AnnotationWithConstants(ClassWithConstants::OTHER_KEY_)',
            ClassWithConstants::OTHER_KEY_,
        ];
        $provider[] = [
            '@AnnotationWithConstants(ClassWithConstants::OTHER_KEY_2)',
            ClassWithConstants::OTHER_KEY_2,
        ];
        $provider[] = [
            '@AnnotationWithConstants(Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithConstants::SOME_VALUE)',
            ClassWithConstants::SOME_VALUE,
        ];
        $provider[] = [
            '@AnnotationWithConstants(IntefaceWithConstants::SOME_VALUE)',
            IntefaceWithConstants::SOME_VALUE,
        ];
        $provider[] = [
            '@AnnotationWithConstants(\Drupal\Tests\Component\Annotation\Doctrine\Fixtures\IntefaceWithConstants::SOME_VALUE)',
            IntefaceWithConstants::SOME_VALUE,
        ];
        $provider[] = [
            '@AnnotationWithConstants({AnnotationWithConstants::STRING, AnnotationWithConstants::INTEGER, AnnotationWithConstants::FLOAT})',
            [AnnotationWithConstants::STRING, AnnotationWithConstants::INTEGER, AnnotationWithConstants::FLOAT],
        ];
        $provider[] = [
            '@AnnotationWithConstants({
                AnnotationWithConstants::STRING = AnnotationWithConstants::INTEGER
             })',
            [AnnotationWithConstants::STRING => AnnotationWithConstants::INTEGER],
        ];
        $provider[] = [
            '@AnnotationWithConstants({
                Drupal\Tests\Component\Annotation\Doctrine\Fixtures\IntefaceWithConstants::SOME_KEY = AnnotationWithConstants::INTEGER
             })',
            [IntefaceWithConstants::SOME_KEY => AnnotationWithConstants::INTEGER],
        ];
        $provider[] = [
            '@AnnotationWithConstants({
                \Drupal\Tests\Component\Annotation\Doctrine\Fixtures\IntefaceWithConstants::SOME_KEY = AnnotationWithConstants::INTEGER
             })',
            [IntefaceWithConstants::SOME_KEY => AnnotationWithConstants::INTEGER],
        ];
        $provider[] = [
            '@AnnotationWithConstants({
                AnnotationWithConstants::STRING = AnnotationWithConstants::INTEGER,
                ClassWithConstants::SOME_KEY = ClassWithConstants::SOME_VALUE,
                Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithConstants::SOME_KEY = IntefaceWithConstants::SOME_VALUE
             })',
            [
                AnnotationWithConstants::STRING => AnnotationWithConstants::INTEGER,
                // Since this class is a near-copy of
                // Doctrine\Tests\Common\Annotations\DocParserTest, we don't fix
                // PHPStan errors here.
                // @phpstan-ignore array.duplicateKey
                ClassWithConstants::SOME_KEY    => ClassWithConstants::SOME_VALUE,
                ClassWithConstants::SOME_KEY    => IntefaceWithConstants::SOME_VALUE,
            ],
        ];
        $provider[] = [
            '@AnnotationWithConstants(AnnotationWithConstants::class)',
            'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants',
        ];
        $provider[] = [
            '@AnnotationWithConstants({AnnotationWithConstants::class = AnnotationWithConstants::class})',
            ['Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants' => 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants'],
        ];
        $provider[] = [
            '@AnnotationWithConstants(Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants::class)',
            'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants',
        ];
        $provider[] = [
            '@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants(Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants::class)',
            'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants',
        ];
        return $provider;
    }

    /**
     * Tests support class constants.
     */
    #[DataProvider('getConstantsProvider')]
    public function testSupportClassConstants($docblock, $expected): void
    {
        $parser = $this->createTestParser();
        $parser->setImports([
            'classwithconstants'        => 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\ClassWithConstants',
            'intefacewithconstants'     => 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\IntefaceWithConstants',
            'annotationwithconstants'   => 'Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants',
        ]);

        $result = $parser->parse($docblock);
        $this->assertInstanceOf('\Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithConstants', $annotation = $result[0]);
        $this->assertEquals($expected, $annotation->value);
    }

    public function testWithoutConstructorWhenIsNotDefaultValue(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('The annotation @SomeAnnotationClassNameWithoutConstructorAndProperties declared on  does not accept any values, but got {"value":"Foo"}.');

        $parser     = $this->createTestParser();
        $docblock   = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructorAndProperties("Foo")
 */
DOCBLOCK;

        $parser->setTarget(Target::TARGET_CLASS);
        $parser->parse($docblock);
    }

    public function testWithoutConstructorWhenHasNoProperties(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('The annotation @SomeAnnotationClassNameWithoutConstructorAndProperties declared on  does not accept any values, but got {"value":"Foo"}.');

        $parser     = $this->createTestParser();
        $docblock   = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructorAndProperties(value = "Foo")
 */
DOCBLOCK;

        $parser->setTarget(Target::TARGET_CLASS);
        $parser->parse($docblock);
    }

    public function testAnnotationTargetSyntaxError(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('Expected namespace separator or identifier, got \')\' at position 24 in class @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithTargetSyntaxError.');

        $parser     = $this->createTestParser();
        $context    = 'class ' . 'SomeClassName';
        $docblock   = <<<DOCBLOCK
/**
 * @Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationWithTargetSyntaxError()
 */
DOCBLOCK;

        $parser->setTarget(Target::TARGET_CLASS);
        $parser->parse($docblock, $context);
    }

    public function testAnnotationWithInvalidTargetDeclarationError(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid Target "Foo". Available targets: [ALL, CLASS, METHOD, PROPERTY, FUNCTION, ANNOTATION]');

        $parser     = $this->createTestParser();
        $context    = 'class ' . 'SomeClassName';
        $docblock   = <<<DOCBLOCK
/**
 * @AnnotationWithInvalidTargetDeclaration()
 */
DOCBLOCK;

        $parser->setTarget(Target::TARGET_CLASS);
        $parser->parse($docblock, $context);
    }

    public function testAnnotationWithTargetEmptyError(): void
    {
        $this->expectException('\InvalidArgumentException');
        $this->expectExceptionMessage('@Target expects either a string value, or an array of strings, "NULL" given.');

        $parser     = $this->createTestParser();
        $context    = 'class ' . 'SomeClassName';
        $docblock   = <<<DOCBLOCK
/**
 * @AnnotationWithTargetEmpty()
 */
DOCBLOCK;

        $parser->setTarget(Target::TARGET_CLASS);
        $parser->parse($docblock, $context);
    }

    /**
     * Tests regression DDC-575.
     */
    #[Group('DDC-575')]
    public function testRegressionDDC575(): void
    {
        $parser = $this->createTestParser();

        $docblock = <<<DOCBLOCK
/**
 * @Name
 *
 * Will trigger error.
 */
DOCBLOCK;

        $result = $parser->parse($docblock);

        $this->assertInstanceOf("Drupal\Tests\Component\Annotation\Doctrine\Name", $result[0]);

        $docblock = <<<DOCBLOCK
/**
 * @Name
 * @Marker
 *
 * Will trigger error.
 */
DOCBLOCK;

        $result = $parser->parse($docblock);

        $this->assertInstanceOf("Drupal\Tests\Component\Annotation\Doctrine\Name", $result[0]);
    }

    /**
     * Tests annotation without class is ignored without warning.
     */
    #[Group('DDC-77')]
    public function testAnnotationWithoutClassIsIgnoredWithoutWarning(): void
    {
        $parser = new DocParser();
        $parser->setIgnoreNotImportedAnnotations(true);
        $result = $parser->parse('@param');

        $this->assertCount(0, $result);
    }

    /**
     * Tests not an annotation class is ignored without warning.
     */
    #[Group('DCOM-168')]
    public function testNotAnAnnotationClassIsIgnoredWithoutWarning(): void
    {
        $parser = new DocParser();
        $parser->setIgnoreNotImportedAnnotations(true);
        $parser->setIgnoredAnnotationNames(['PHPUnit_Framework_TestCase' => true]);
        $result = $parser->parse('@PHPUnit_Framework_TestCase');

        $this->assertCount(0, $result);
    }

    public function testAnnotationDontAcceptSingleQuotes(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('Expected PlainValue, got \'\'\' at position 10.');

        $parser = $this->createTestParser();
        $parser->parse("@Name(foo='bar')");
    }

    /**
     * Tests annotation does not throw exception when at sign is not followed by identifier.
     */
    #[Group('DCOM-41')]
    public function testAnnotationDoesNotThrowExceptionWhenAtSignIsNotFollowedByIdentifier(): void
    {
        $parser = new DocParser();
        $result = $parser->parse("'@'");

        $this->assertCount(0, $result);
    }

    /**
     * Tests annotation throws exception when at sign is not followed by identifier in nested annotation.
     */
    #[Group('DCOM-41')]
    public function testAnnotationThrowsExceptionWhenAtSignIsNotFollowedByIdentifierInNestedAnnotation(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');

        $parser = new DocParser();
        $parser->parse("@Drupal\Tests\Component\Annotation\Doctrine\Name(@')");
    }

    /**
     * Tests autoload annotation.
     */
    #[Group('DCOM-56')]
    public function testAutoloadAnnotation(): void
    {
        self::assertFalse(
            class_exists('Drupal\Tests\Component\Annotation\Doctrine\Fixture\Annotation\Autoload', false),
            'Pre-condition: Drupal\Tests\Component\Annotation\Doctrine\Fixture\Annotation\Autoload not allowed to be loaded.'
        );

        $parser = new DocParser();

        $parser->setImports([
          'autoload' => Autoload::class,
        ]);
        $annotations = $parser->parse('@Autoload');

        self::assertCount(1, $annotations);
        self::assertInstanceOf(Autoload::class, $annotations[0]);
    }

    /**
     * @phpstan-ignore missingType.return
     */
    public function createTestParser()
    {
        $parser = new DocParser();
        $parser->setIgnoreNotImportedAnnotations(true);
        $parser->setImports([
            'name' => 'Drupal\Tests\Component\Annotation\Doctrine\Name',
            '__NAMESPACE__' => 'Drupal\Tests\Component\Annotation\Doctrine',
        ]);

        return $parser;
    }

    /**
     * Tests syntax error with context description.
     */
    #[Group('DDC-78')]
    public function testSyntaxErrorWithContextDescription(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('Expected PlainValue, got \'\'\' at position 10 in class \Drupal\Tests\Component\Annotation\Doctrine\Name');

        $parser = $this->createTestParser();
        $parser->parse("@Name(foo='bar')", "class \Drupal\Tests\Component\Annotation\Doctrine\Name");
    }

    /**
     * Tests syntax error with unknown characters.
     */
    #[Group('DDC-183')]
    public function testSyntaxErrorWithUnknownCharacters(): void
    {
        $docblock = <<<DOCBLOCK
/**
 * @test at.
 */
class A {
}
DOCBLOCK;

        //$lexer = new \Doctrine\Common\Annotations\Lexer();
        //$lexer->setInput(trim($docblock, '/ *'));
        //var_dump($lexer);

        try {
            $parser = $this->createTestParser();
            $result = $parser->parse($docblock);
            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * Tests ignore PHPDoc throw tag.
     */
    #[Group('DCOM-14')]
    public function testIgnorePHPDocThrowTag(): void
    {
        $docblock = <<<DOCBLOCK
/**
 * @throws \RuntimeException
 */
class A {
}
DOCBLOCK;

        try {
            $parser = $this->createTestParser();
            $result = $parser->parse($docblock);
            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * Tests cast int.
     */
    #[Group('DCOM-38')]
    public function testCastInt(): void
    {
        $parser = $this->createTestParser();

        $result = $parser->parse('@Name(foo=1234)');
        $annot = $result[0];
        $this->assertIsInt($annot->foo);
    }

    /**
     * Tests cast negative int.
     */
    #[Group('DCOM-38')]
    public function testCastNegativeInt(): void
    {
        $parser = $this->createTestParser();

        $result = $parser->parse('@Name(foo=-1234)');
        $annot = $result[0];
        $this->assertIsInt($annot->foo);
    }

    /**
     * Tests cast float.
     */
    #[Group('DCOM-38')]
    public function testCastFloat(): void
    {
        $parser = $this->createTestParser();

        $result = $parser->parse('@Name(foo=1234.345)');
        $annot = $result[0];
        $this->assertIsFloat($annot->foo);
    }

    /**
     * Tests cast negative float.
     */
    #[Group('DCOM-38')]
    public function testCastNegativeFloat(): void
    {
        $parser = $this->createTestParser();

        $result = $parser->parse('@Name(foo=-1234.345)');
        $annot = $result[0];
        $this->assertIsFloat($annot->foo);

        $result = $parser->parse('@Marker(-1234.345)');
        $annot = $result[0];
        $this->assertIsFloat($annot->value);
    }

    public function testSetValuesException(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('[Creation Error] The annotation @SomeAnnotationClassNameWithoutConstructor declared on some class does not have a property named "invalidaProperty". Available properties: data, name');

        $docblock = <<<DOCBLOCK
/**
 * @SomeAnnotationClassNameWithoutConstructor(invalidaProperty = "Some val")
 */
DOCBLOCK;

        $this->createTestParser()->parse($docblock, 'some class');
    }

    public function testInvalidIdentifierInAnnotation(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('[Syntax Error] Expected Drupal\Component\Annotation\Doctrine\DocLexer::T_IDENTIFIER or Drupal\Component\Annotation\Doctrine\DocLexer::T_TRUE or Drupal\Component\Annotation\Doctrine\DocLexer::T_FALSE or Drupal\Component\Annotation\Doctrine\DocLexer::T_NULL, got \'3.42\' at position 5.');

        $parser = $this->createTestParser();
        $parser->parse('@Foo\3.42');
    }

    public function testTrailingCommaIsAllowed(): void
    {
        $parser = $this->createTestParser();

        $annots = $parser->parse('@Name({
            "Foo",
            "Bar",
        })');
        $this->assertCount(1, $annots);
        $this->assertEquals(['Foo', 'Bar'], $annots[0]->value);
    }

    public function testDefaultAnnotationValueIsNotOverwritten(): void
    {
        $parser = $this->createTestParser();

        $annots = $parser->parse('@Drupal\Tests\Component\Annotation\Doctrine\Fixtures\Annotation\AnnotWithDefaultValue');
        $this->assertCount(1, $annots);
        $this->assertEquals('bar', $annots[0]->foo);
    }

    public function testArrayWithColon(): void
    {
        $parser = $this->createTestParser();

        $annots = $parser->parse('@Name({"foo": "bar"})');
        $this->assertCount(1, $annots);
        $this->assertEquals(['foo' => 'bar'], $annots[0]->value);
    }

    public function testInvalidContantName(): void
    {
        $this->expectException('\Drupal\Component\Annotation\Doctrine\AnnotationException');
        $this->expectExceptionMessage('[Semantical Error] Couldn\'t find constant foo.');

        $parser = $this->createTestParser();
        $parser->parse('@Name(foo: "bar")');
    }

    /**
     * Tests parsing empty arrays.
     */
    public function testEmptyArray(): void
    {
        $parser = $this->createTestParser();

        $annots = $parser->parse('@Name({"foo": {}})');
        $this->assertCount(1, $annots);
        $this->assertEquals(['foo' => []], $annots[0]->value);
    }

    public function testKeyHasNumber(): void
    {
        $parser = $this->createTestParser();
        $annots = $parser->parse('@SettingsAnnotation(foo="test", bar2="test")');

        $this->assertCount(1, $annots);
        $this->assertEquals(['foo' => 'test', 'bar2' => 'test'], $annots[0]->settings);
    }

    /**
     * Tests supports escaped quoted values.
     */
    #[Group('44')]
    public function testSupportsEscapedQuotedValues(): void
    {
        $result = $this->createTestParser()->parse('@Drupal\Tests\Component\Annotation\Doctrine\Name(foo="""bar""")');

        $this->assertCount(1, $result);

        $this->assertInstanceOf(Name::class, $result[0]);
        $this->assertEquals('"bar"', $result[0]->foo);
    }
}

/** @Annotation */
class SettingsAnnotation
{
    public $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }
}

/** @Annotation */
class SomeAnnotationClassNameWithoutConstructor
{
    public $data;
    public $name;
}

/** @Annotation */
class SomeAnnotationWithConstructorWithoutParams
{
    public function __construct()
    {
        $this->data = 'Some data';
    }
    public $data;
    public $name;
}

/** @Annotation */
class SomeAnnotationClassNameWithoutConstructorAndProperties
{
}

/**
 * @Annotation
 * @Target("Foo")
 */
class AnnotationWithInvalidTargetDeclaration
{
}

/**
 * @Annotation
 * @Target
 */
class AnnotationWithTargetEmpty
{
}

/** @Annotation */
class AnnotationExtendsAnnotationTargetAll extends \Drupal\Tests\Component\Annotation\Doctrine\Fixtures\AnnotationTargetAll
{
}

/** @Annotation */
class Name extends \Drupal\Component\Annotation\Doctrine\Annotation
{
    public $foo;
}

/** @Annotation */
class Marker
{
    public $value;
}

namespace Drupal\Tests\Component\Annotation\Doctrine\FooBar;

/** @Annotation */
class Name extends \Drupal\Component\Annotation\Doctrine\Annotation
{
}
