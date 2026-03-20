<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\Unit;

use Drupal\ckeditor5\HTMLRestrictions;
use Drupal\filter\FilterFormatInterface;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\ckeditor5\HTMLRestrictions.
 */
#[CoversClass(HTMLRestrictions::class)]
#[Group('ckeditor5')]
class HTMLRestrictionsTest extends UnitTestCase
{
    /**
     * Tests constructor.
     *
     * @legacy-covers ::__construct
     */
    #[DataProvider('providerConstruct')]
    public function testConstructor(array $elements, ?string $expected_exception_message): void
    {
        if ($expected_exception_message !== null) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage($expected_exception_message);
        }
        $restrictions = new HTMLRestrictions($elements);
        $this->assertIsArray($restrictions->getAllowedElements(false));
    }

    /**
     * Provides data to testConstructor().
     */
    public static function providerConstruct(): \Generator
    {
        // Fundamental structure.
        yield 'INVALID: list instead of key-value pairs' => [
          ['<foo>', '<bar>'],
          'An array of key-value pairs must be provided, with HTML tag names as keys.',
        ];

        // Invalid HTML tag names.
        yield 'INVALID: key-value pairs now, but invalid keys due to angular brackets' => [
          ['<foo>' => '', '<bar> ' => ''],
          '"<foo>" is not a HTML tag name, it is an actual HTML tag. Omit the angular brackets.',
        ];
        yield 'INVALID: no more angular brackets, but still leading or trailing whitespace' => [
          ['foo' => '', 'bar ' => ''],
          'The "bar " HTML tag contains trailing or leading whitespace.',
        ];
        yield 'INVALID: invalid character range' => [
          ['🦙' => ''],
          '"🦙" is not a valid HTML tag name.',
        ];
        yield 'INVALID: invalid custom element name' => [
          ['foo-bar' => '', '1-foo-bar' => ''],
          '"1-foo-bar" is not a valid HTML tag name.',
        ];
        yield 'INVALID: unknown wildcard element name' => [
          ['$foo' => true],
          '"$foo" is not a valid HTML tag name.',
        ];

        // Invalid HTML tag attribute name restrictions.
        yield 'INVALID: keys valid, but not yet the values' => [
          ['foo' => '', 'bar' => ''],
          'The value for the "foo" HTML tag is neither a boolean nor an array of attribute restrictions.',
        ];
        yield 'INVALID: keys valid, values can be arrays … but not empty arrays' => [
          ['foo' => [], 'bar' => []],
          'The value for the "foo" HTML tag is an empty array. This is not permitted, specify FALSE instead to indicate no attributes are allowed. Otherwise, list allowed attributes.',
        ];
        yield 'INVALID: keys valid, values invalid attribute restrictions' => [
          ['foo' => ['baz'], 'bar' => [' qux']],
          'The "foo" HTML tag has attribute restrictions, but it is not an array of key-value pairs, with HTML tag attribute names as keys.',
        ];
        yield 'INVALID: keys valid, values invalid attribute restrictions due to invalid attribute name' => [
          ['foo' => ['baz' => ''], 'bar' => [' qux' => '']],
          'The "bar" HTML tag has an attribute restriction " qux" which contains whitespace. Omit the whitespace.',
        ];
        yield 'INVALID: keys valid, values invalid attribute restrictions due to broad wildcard instead of prefix/infix/suffix wildcard attribute name' => [
          ['foo' => ['*' => true]],
          'The "foo" HTML tag has an attribute restriction "*". This implies all attributes are allowed. Remove the attribute restriction instead, or use a prefix (`*-foo`), infix (`*-foo-*`) or suffix (`foo-*`) wildcard restriction instead.',
        ];

        // Invalid HTML tag attribute value restrictions.
        yield 'INVALID: keys valid, values invalid attribute restrictions due to empty strings' => [
          ['foo' => ['baz' => ''], 'bar' => ['qux' => '']],
          'The "foo" HTML tag has an attribute restriction "baz" which is neither TRUE nor an array of attribute value restrictions.',
        ];
        yield 'INVALID: keys valid, values invalid attribute restrictions due to an empty array of allowed attribute values' => [
          ['foo' => ['baz' => true], 'bar' => ['qux' => []]],
          'The "bar" HTML tag has an attribute restriction "qux" which is set to the empty array. This is not permitted, specify either TRUE to allow all attribute values, or list the attribute value restrictions.',
        ];
        yield 'INVALID: keys valid, values invalid attribute restrictions due to a list of allowed attribute values' => [
          ['foo' => ['baz' => true], 'bar' => ['qux' => ['a', 'b']]],
          'The "bar" HTML tag has attribute restriction "qux", but it is not an array of key-value pairs, with HTML tag attribute values as keys and TRUE as values.',
        ];
        yield 'INVALID: keys valid, values invalid attribute restrictions due to broad wildcard instead of prefix/infix/suffix wildcard allowed attribute value' => [
          ['foo' => ['bar' => ['*' => true]]],
          'The "foo" HTML tag has an attribute restriction "bar" with a "*" allowed attribute value. This implies all attributes values are allowed. Remove the attribute value restriction instead, or use a prefix (`*-foo`), infix (`*-foo-*`) or suffix (`foo-*`) wildcard restriction instead.',
        ];

        // Valid values.
        yield 'VALID: keys valid, boolean attribute restriction values: also valid' => [
          ['foo' => true, 'bar' => false],
          null,
        ];
        yield 'VALID: keys valid, array attribute restriction values: also valid' => [
          ['foo' => ['baz' => true], 'bar' => ['qux' => ['a' => true, 'b' => true]]],
          null,
        ];

        // Invalid global attribute `*` HTML tag restrictions.
        yield 'INVALID: global attribute tag allowing no attributes' => [
          ['*' => false],
          'The value for the special "*" global attribute HTML tag must be an array of attribute restrictions.',
        ];
        yield 'INVALID: global attribute tag allowing any attribute' => [
          ['*' => true],
          'The value for the special "*" global attribute HTML tag must be an array of attribute restrictions.',
        ];

        // Valid global attribute `*` HTML tag restrictions.
        yield 'VALID: global attribute tag with attribute allowed' => [
          ['*' => ['foo' => true]],
          null,
        ];
        yield 'VALID: global attribute tag with attribute forbidden' => [
          ['*' => ['foo' => false]],
          null,
        ];
        yield 'VALID: global attribute tag with attribute allowed, specific attribute values allowed' => [
          ['*' => ['foo' => ['a' => true, 'b' => true]]],
          null,
        ];
        yield 'VALID BUT NOT YET SUPPORTED: global attribute tag with attribute allowed, specific attribute values forbidden' => [
          ['*' => ['foo' => ['a' => false, 'b' => false]]],
          'The "*" HTML tag has attribute restriction "foo", but it is not an array of key-value pairs, with HTML tag attribute values as keys and TRUE as values.',
        ];

        // Invalid overrides of globally disallowed attributes.
        yield 'INVALID: <foo bar> when "bar" is globally disallowed' => [
          ['foo' => ['bar' => true], '*' => ['bar' => false, 'baz' => true]],
          'The attribute restrictions in "<foo bar>" are allowing attributes "bar" that are disallowed by the special "*" global attribute restrictions',
        ];
        yield 'INVALID: <foo style> when "style" is globally disallowed' => [
          ['foo' => ['style' => true], '*' => ['bar' => false, 'baz' => true, 'style' => false]],
          'The attribute restrictions in "<foo style>" are allowing attributes "bar", "style" that are disallowed by the special "*" global attribute restrictions',
        ];
        yield 'INVALID: <foo on*> when "on*" is globally disallowed' => [
          ['foo' => ['on*' => true], '*' => ['bar' => false, 'baz' => true, 'style' => false, 'on*' => false]],
          'The attribute restrictions in "<foo on*>" are allowing attributes "bar", "style", "on*" that are disallowed by the special "*" global attribute restrictions',
        ];
        yield 'INVALID: <foo ontouch> when "on" is globally disallowed' => [
          ['foo' => ['ontouch' => true], '*' => ['bar' => false, 'baz' => true, 'style' => false, 'on*' => false]],
          'The attribute restrictions in "<foo ontouch>" are allowing attributes "bar", "style", "on*" that are disallowed by the special "*" global attribute restrictions',
        ];
    }

    /**
     * Tests counting.
     *
     * @legacy-covers ::allowsNothing
     * @legacy-covers ::getAllowedElements
     */
    #[DataProvider('providerCounting')]
    public function testCounting(array $elements, bool $expected_is_empty, int $expected_concrete_only_count, int $expected_concrete_plus_wildcard_count): void
    {
        $r = new HTMLRestrictions($elements);
        $this->assertSame($expected_is_empty, $r->allowsNothing());
        $this->assertCount($expected_concrete_only_count, $r->getAllowedElements());
        $this->assertCount($expected_concrete_only_count, $r->getAllowedElements(true));
        $this->assertCount($expected_concrete_plus_wildcard_count, $r->getAllowedElements(false));
    }

    /**
     * Provides data to testCounting().
     */
    public static function providerCounting(): \Generator
    {
        yield 'empty' => [
          [],
          true,
          0,
          0,
        ];

        yield 'one concrete tag' => [
          ['a' => true],
          false,
          1,
          1,
        ];

        yield 'one wildcard tag: considered to allow nothing because no concrete tag to resolve onto' => [
          ['$text-container' => ['class' => ['text-align-left' => true]]],
          false,
          0,
          1,
        ];

        yield 'two concrete tags' => [
          ['a' => true, 'b' => false],
          false,
          2,
          2,
        ];

        yield 'one concrete tag, one wildcard tag' => [
          ['a' => true, '$text-container' => ['class' => ['text-align-left' => true]]],
          false,
          1,
          2,
        ];

        yield 'only globally allowed attribute: considered to allow something' => [
          ['*' => ['lang' => true]],
          false,
          1,
          1,
        ];

        yield 'only globally forbidden attribute: considered to allow nothing' => [
          ['*' => ['style' => false]],
          true,
          1,
          1,
        ];
    }

    /**
     * Tests convenience constructors.
     *
     * @legacy-covers ::fromString
     * @legacy-covers ::fromTextFormat
     * @legacy-covers ::fromFilterPluginInstance
     */
    #[DataProvider('providerConvenienceConstructors')]
    public function testConvenienceConstructors($input, array $expected, ?array $expected_raw = null): void
    {
        $expected_raw = $expected_raw ?? $expected;

        // ::fromString()
        $this->assertSame($expected, HTMLRestrictions::fromString($input)->getAllowedElements());
        $this->assertSame($expected_raw, HTMLRestrictions::fromString($input)->getAllowedElements(false));

        // ::fromTextFormat()
        $text_format = $this->prophesize(FilterFormatInterface::class);
        $text_format->getHTMLRestrictions()->willReturn([
          'allowed' => $expected_raw,
        ]);
        $this->assertSame($expected, HTMLRestrictions::fromTextFormat($text_format->reveal())->getAllowedElements());
        $this->assertSame($expected_raw, HTMLRestrictions::fromTextFormat($text_format->reveal())->getAllowedElements(false));

        // @see \Drupal\filter\Plugin\Filter\FilterHtml::getHTMLRestrictions()
        $filter_html_additional_expectations = [
          '*' => [
            'style' => false,
            'on*' => false,
            'lang' => true,
            'dir' => ['ltr' => true, 'rtl' => true],
          ],
        ];
        // ::fromFilterPluginInstance()
        $filter_plugin_instance = $this->prophesize(FilterInterface::class);
        $filter_plugin_instance->getHTMLRestrictions()->willReturn([
          'allowed' => $expected_raw + $filter_html_additional_expectations,
        ]);
        $this->assertSame($expected + $filter_html_additional_expectations, HTMLRestrictions::fromFilterPluginInstance($filter_plugin_instance->reveal())->getAllowedElements());
        $this->assertSame($expected_raw + $filter_html_additional_expectations, HTMLRestrictions::fromFilterPluginInstance($filter_plugin_instance->reveal())->getAllowedElements(false));
    }

    /**
     * Provides data to testConvenienceConstructors().
     */
    public static function providerConvenienceConstructors(): \Generator
    {
        // All empty cases.
        yield 'empty string' => [
          '',
          [],
        ];
        yield 'empty array' => [
          implode(' ', []),
          [],
        ];
        yield 'whitespace string' => [
          '             ',
          [],
        ];

        // Some nonsense cases.
        yield 'nonsense string' => [
          'Hello there, this looks nothing like a HTML restriction.',
          [],
        ];
        yield 'nonsense array #1' => [
          implode(' ', ['foo', 'bar']),
          [],
        ];
        yield 'nonsense array #2' => [
          implode(' ', ['foo' => true, 'bar' => false]),
          [],
        ];

        // Single tag cases.
        yield 'tag without attributes' => [
          '<a>',
          ['a' => false],
        ];
        yield 'tag with wildcard attribute' => [
          '<a *>',
          ['a' => true],
        ];
        yield 'tag with single attribute allowing any value' => [
          '<a target>',
          ['a' => ['target' => true]],
        ];
        yield 'tag with single attribute allowing any value unnecessarily explicitly' => [
          '<a target="*">',
          ['a' => ['target' => true]],
        ];
        yield 'tag with single attribute allowing single specific value' => [
          '<a target="_blank">',
          ['a' => ['target' => ['_blank' => true]]],
        ];
        yield 'tag with single attribute allowing multiple specific values' => [
          '<a target="_self _blank">',
          ['a' => ['target' => ['_self' => true, '_blank' => true]]],
        ];
        yield 'tag with single attribute allowing multiple specific values (reverse order)' => [
          '<a target="_blank _self">',
          ['a' => ['target' => ['_blank' => true, '_self' => true]]],
        ];
        yield 'tag with two attributes' => [
          '<a target class>',
          ['a' => ['target' => true, 'class' => true]],
        ];
        yield 'tag with allowed attribute value that happen to be numbers' => [
          '<ol type="1 A I">',
          ['ol' => ['type' => [1 => true, 'A' => true, 'I' => true]]],
        ];
        yield 'tag with allowed attribute value that happen to be numbers (reversed)' => [
          '<ol type="I A 1">',
          ['ol' => ['type' => ['I' => true, 'A' => true, 1 => true]]],
        ];
        yield 'tag with two attributes, spread across declarations' => [
          '<a target> <a class>',
          ['a' => ['target' => true, 'class' => true]],
        ];
        yield 'tag with conflicting attribute config, allow one attribute and forbid all attributes' => [
          '<a target> <a>',
          ['a' => ['target' => true]],
        ];
        yield 'tag with conflicting attribute config, allow one attribute and allow all attributes' => [
          '<a *> <a target>',
          ['a' => true],
        ];
        yield 'tag attribute configuration spread across declarations' => [
          '<a target="_blank"> <a target="_self"> <a target="_*">',
          ['a' => ['target' => ['_blank' => true, '_self' => true, '_*' => true]]],
        ];
        yield 'tag attribute configuration spread across declarations, allow all attributes values' => [
          '<a target> <a target="_blank"> <a target="_self"> <a target="_*">',
          ['a' => ['target' => true]],
        ];

        // Multiple tag cases.
        yield 'two tags' => [
          '<a> <p>',
          ['a' => false, 'p' => false],
        ];
        yield 'two tags (reverse order)' => [
          '<p> <a>',
          ['p' => false, 'a' => false],
        ];

        // Wildcard tag, attribute and attribute value.
        yield '$text-container' => [
          '<$text-container class="text-align-left text-align-center text-align-right text-align-justify">',
          [],
          [
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '$text-container, with attribute values spread across declarations' => [
          '<$text-container class="text-align-left"> <$text-container class="text-align-center"> <$text-container class="text-align-right"> <$text-container class="text-align-justify">',
          [],
          [
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '$text-container + one concrete tag to resolve into' => [
          '<p> <$text-container class="text-align-left text-align-center text-align-right text-align-justify">',
          [
            'p' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
          [
            'p' => false,
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '$text-container + two concrete tag to resolve into' => [
          '<p> <$text-container class="text-align-left text-align-center text-align-right text-align-justify"> <div>',
          [
            'p' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
            'div' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
          [
            'p' => false,
            'div' => false,
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '$text-container + one concrete tag to resolve into that already allows a subset of attributes: concrete less permissive than wildcard' => [
          '<p class="text-align-left"> <$text-container class="text-align-left text-align-center text-align-right text-align-justify">',
          [
            'p' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
          [
            'p' => [
              'class' => [
                'text-align-left' => true,
              ],
            ],
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '$text-container + one concrete tag to resolve into that already allows all attribute values: concrete more permissive than wildcard' => [
          '<p class> <$text-container class="text-align-left text-align-center text-align-right text-align-justify">',
          [
            'p' => [
              'class' => true,
            ],
          ],
          [
            'p' => [
              'class' => true,
            ],
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '$text-container + one concrete tag to resolve into that already allows all attributes: concrete more permissive than wildcard' => [
          '<p *> <$text-container class="text-align-left text-align-center text-align-right text-align-justify">',
          [
            'p' => true,
          ],
          [
            'p' => true,
            '$text-container' => [
              'class' => [
                'text-align-left' => true,
                'text-align-center' => true,
                'text-align-right' => true,
                'text-align-justify' => true,
              ],
            ],
          ],
        ];
        yield '<drupal-media data-*>' => [
          '<drupal-media data-*>',
          ['drupal-media' => ['data-*' => true]],
        ];
        yield '<drupal-media foo-*-bar>' => [
          '<drupal-media foo-*-bar>',
          ['drupal-media' => ['foo-*-bar' => true]],
        ];
        yield '<drupal-media *-foo>' => [
          '<drupal-media *-foo>',
          ['drupal-media' => ['*-foo' => true]],
        ];
        yield '<h2 id="jump-*">' => [
          '<h2 id="jump-*">',
          ['h2' => ['id' => ['jump-*' => true]]],
        ];

        // Attribute restrictions that match the global attribute restrictions
        // should be omitted from concrete tags.
        yield '<p> <* foo>' => [
          '<p> <* foo>',
          ['p' => false, '*' => ['foo' => true]],
        ];
        yield '<p foo> <* foo> results in <p> getting simplified' => [
          '<p foo> <* foo>',
          ['p' => false, '*' => ['foo' => true]],
        ];
        yield '<* foo> <p foo> results in <p> getting simplified' => [
          '<* foo> <p foo>',
          ['p' => false, '*' => ['foo' => true]],
        ];
        yield '<p foo bar> <* foo> results in <p> getting simplified' => [
          '<p foo bar> <* foo>',
          ['p' => ['bar' => true], '*' => ['foo' => true]],
        ];
        yield '<* foo> <p foo bar> results in <p> getting simplified' => [
          '<* foo> <p foo bar>',
          ['p' => ['bar' => true], '*' => ['foo' => true]],
        ];
        yield '<p foo="a b"> + <* foo="b a"> results in <p> getting simplified' => [
          '<p foo="a b"> <* foo="b a">',
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<* foo="b a"> <p foo="a b"> results in <p> getting simplified' => [
          '<* foo="b a"> <p foo="a b">',
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<p foo="a b" bar> + <* foo="b a"> results in <p> getting simplified' => [
          '<p foo="a b" bar> <* foo="b a">',
          ['p' => ['bar' => true], '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<* foo="b a"> <p foo="a b" bar> results in <p> getting simplified' => [
          '<* foo="b a"> <p foo="a b" bar>',
          ['p' => ['bar' => true], '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<p foo="a b c"> + <* foo="b a"> results in <p> getting simplified' => [
          '<p foo="a b c"> <* foo="b a">',
          ['p' => ['foo' => ['c' => true]], '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<* foo="b a"> <p foo="a b c"> results in <p> getting simplified' => [
          '<* foo="b a"> <p foo="a b c">',
          ['p' => ['foo' => ['c' => true]], '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        // Attribute restrictions that match the global attribute restrictions
        // should be omitted from wildcard tags.
        yield '<p> <$text-container foo> <* foo> results in <$text-container> getting simplified' => [
          '<p> <$text-container foo> <* foo>',
          ['p' => false, '*' => ['foo' => true]],
          ['p' => false, '$text-container' => false, '*' => ['foo' => true]],
        ];
        yield '<* foo> <text-container foo> <p> results in <$text-container> getting stripped' => [
          '<* foo> <p> <$text-container foo>',
          ['p' => false, '*' => ['foo' => true]],
          ['p' => false, '*' => ['foo' => true], '$text-container' => false],
        ];
        yield '<p> <$text-container foo bar> <* foo> results in <$text-container> getting simplified' => [
          '<p> <$text-container foo bar> <* foo>',
          ['p' => ['bar' => true], '*' => ['foo' => true]],
          ['p' => false, '$text-container' => ['bar' => true], '*' => ['foo' => true]],
        ];
        yield '<* foo> <$text-container foo bar> <p> results in <$text-container> getting simplified' => [
          '<* foo> <$text-container foo bar> <p>',
          ['p' => ['bar' => true], '*' => ['foo' => true]],
          ['p' => false, '*' => ['foo' => true], '$text-container' => ['bar' => true]],
        ];
        yield '<p> <$text-container foo="a b"> + <* foo="b a"> results in <$text-container> getting simplified' => [
          '<p> <$text-container foo="a b"> <* foo="b a">',
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]]],
          ['p' => false, '$text-container' => false, '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<* foo="b a"> <p> <$text-container foo="a b"> results in <$text-container> getting simplified' => [
          '<* foo="b a"> <p> <$text-container foo="a b">',
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]]],
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]], '$text-container' => false],
        ];
        yield '<p> <$text-container foo="a b" bar> + <* foo="b a"> results in <$text-container> getting simplified' => [
          '<p> <$text-container foo="a b" bar> <* foo="b a">',
          ['p' => ['bar' => true], '*' => ['foo' => ['b' => true, 'a' => true]]],
          ['p' => false, '$text-container' => ['bar' => true], '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<* foo="b a"> <p> <$text-container foo="a b" bar> results in <$text-container> getting simplified' => [
          '<* foo="b a"> <p> <$text-container foo="a b" bar>',
          ['p' => ['bar' => true], '*' => ['foo' => ['b' => true, 'a' => true]]],
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]], '$text-container' => ['bar' => true]],
        ];
        yield '<p> <$text-container foo="a b c"> + <* foo="b a"> results in <$text-container> getting simplified' => [
          '<p> <$text-container foo="a b c"> <* foo="b a">',
          ['p' => ['foo' => ['c' => true]], '*' => ['foo' => ['b' => true, 'a' => true]]],
          ['p' => false, '$text-container' => ['foo' => ['c' => true]], '*' => ['foo' => ['b' => true, 'a' => true]]],
        ];
        yield '<* foo="b a"> <p> <$text-container foo="a b c"> results in <$text-container> getting simplified' => [
          '<* foo="b a"> <p> <$text-container foo="a b c">',
          ['p' => ['foo' => ['c' => true]], '*' => ['foo' => ['b' => true, 'a' => true]]],
          ['p' => false, '*' => ['foo' => ['b' => true, 'a' => true]], '$text-container' => ['foo' => ['c' => true]]],
        ];
    }

    /**
     * Tests representations.
     *
     * @legacy-covers ::toCKEditor5ElementsArray
     * @legacy-covers ::toFilterHtmlAllowedTagsString
     * @legacy-covers ::toGeneralHtmlSupportConfig
     */
    #[DataProvider('providerRepresentations')]
    public function testRepresentations(HTMLRestrictions $restrictions, array $expected_elements_array, string $expected_allowed_html_string, array $expected_ghs_config): void
    {
        $this->assertSame($expected_elements_array, $restrictions->toCKEditor5ElementsArray());
        $this->assertSame($expected_allowed_html_string, $restrictions->toFilterHtmlAllowedTagsString());
        $this->assertSame($expected_ghs_config, $restrictions->toGeneralHtmlSupportConfig());
    }

    /**
     * Provides data to testRepresentations().
     */
    public static function providerRepresentations(): \Generator
    {
        yield 'empty set' => [
          HTMLRestrictions::emptySet(),
          [],
          '',
          [],
        ];

        yield 'only tags' => [
          new HTMLRestrictions(['a' => false, 'p' => false, 'br' => false]),
          ['<a>', '<p>', '<br>'],
          '<a> <p> <br>',
          [
            ['name' => 'a'],
            ['name' => 'p'],
            ['name' => 'br'],
          ],
        ];

        yield 'single tag with multiple attributes allowing all values' => [
          new HTMLRestrictions(['script' => ['src' => true, 'defer' => true]]),
          ['<script src defer>'],
          '<script src defer>',
          [
            [
              'name' => 'script',
              'attributes' => [
                ['key' => 'src', 'value' => true],
                ['key' => 'defer', 'value' => true],
              ],
            ],
          ],
        ];

        yield '$text-container wildcard' => [
          new HTMLRestrictions([
            '$text-container' => [
              'class' => true,
              'data-llama' => true,
            ],
            'div' => false,
            'span' => false,
            'p' => ['id' => true],
          ]),
          ['<$text-container class data-llama>', '<div>', '<span>', '<p id>'],
          '<div class data-llama> <span> <p id class data-llama>',
          [
            [
              'name' => 'div',
              'classes' => true,
              'attributes' => [
                [
                  'key' => 'data-llama',
                  'value' => true,
                ],
              ],
            ],
            ['name' => 'span'],
            [
              'name' => 'p',
              'attributes' => [
                [
                  'key' => 'id',
                  'value' => true,
                ],
                [
                  'key' => 'data-llama',
                  'value' => true,
                ],
              ],
              'classes' => true,
            ],
          ],
        ];

        yield 'realistic' => [
          new HTMLRestrictions([
            'a' => [
              'href' => true,
              'hreflang' => ['en' => true, 'fr' => true],
            ],
            'p' => ['data-*' => true, 'class' => ['block' => true]],
            'br' => false,
          ]),
          ['<a href hreflang="en fr">', '<p data-* class="block">', '<br>'],
          '<a href hreflang="en fr"> <p data-* class="block"> <br>',
          [
            [
              'name' => 'a',
              'attributes' => [
                ['key' => 'href', 'value' => true],
                [
                  'key' => 'hreflang',
                  'value' => [
                    'regexp' => [
                      'pattern' => '/^(en|fr)$/',
                    ],
                  ],
                ],
              ],
            ],
            [
              'name' => 'p',
              'attributes' => [
                [
                  'key' => [
                    'regexp' => [
                      'pattern' => '/^data-.*$/',
                    ],
                  ],
                  'value' => true,
                ],
              ],
              'classes' => [
                'regexp' => [
                  'pattern' => '/^(block)$/',
                ],
              ],
            ],
            ['name' => 'br'],
          ],
        ];

        // Wildcard tag, attribute and attribute value.
        yield '$text-container' => [
          new HTMLRestrictions(['p' => false, '$text-container' => ['data-*' => true]]),
          ['<p>', '<$text-container data-*>'],
          '<p data-*>',
          [
            [
              'name' => 'p',
              'attributes' => [
                [
                  'key' => [
                    'regexp' => [
                      'pattern' => '/^data-.*$/',
                    ],
                  ],
                  'value' => true,
                ],
              ],
            ],
          ],
        ];
        yield '<drupal-media data-*>' => [
          new HTMLRestrictions(['drupal-media' => ['data-*' => true]]),
          ['<drupal-media data-*>'],
          '<drupal-media data-*>',
          [
            [
              'name' => 'drupal-media',
              'attributes' => [
                [
                  'key' => [
                    'regexp' => [
                      'pattern' => '/^data-.*$/',
                    ],
                  ],
                  'value' => true,
                ],
              ],
            ],
          ],
        ];
        yield '<drupal-media foo-*-bar>' => [
          new HTMLRestrictions(['drupal-media' => ['foo-*-bar' => true]]),
          ['<drupal-media foo-*-bar>'],
          '<drupal-media foo-*-bar>',
          [
            [
              'name' => 'drupal-media',
              'attributes' => [
                [
                  'key' => [
                    'regexp' => [
                      'pattern' => '/^foo-.*-bar$/',
                    ],
                  ],
                  'value' => true,
                ],
              ],
            ],
          ],
        ];
        yield '<drupal-media *-bar>' => [
          new HTMLRestrictions(['drupal-media' => ['*-bar' => true]]),
          ['<drupal-media *-bar>'],
          '<drupal-media *-bar>',
          [
            [
              'name' => 'drupal-media',
              'attributes' => [
                [
                  'key' => [
                    'regexp' => [
                      'pattern' => '/^.*-bar$/',
                    ],
                  ],
                  'value' => true,
                ],
              ],
            ],
          ],
        ];
        yield '<h2 id="jump-*">' => [
          new HTMLRestrictions(['h2' => ['id' => ['jump-*' => true]]]),
          ['<h2 id="jump-*">'],
          '<h2 id="jump-*">',
          [
            [
              'name' => 'h2',
              'attributes' => [
                [
                  'key' => 'id',
                  'value' => [
                    'regexp' => [
                      'pattern' => '/^(jump-.*)$/',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];

        yield '<ol type="1 A">' => [
          new HTMLRestrictions(['ol' => ['type' => ['1' => true, 'A' => true]]]),
          ['<ol type="1 A">'],
          '<ol type="1 A">',
          [
            [
              'name' => 'ol',
              'attributes' => [
                [
                  'key' => 'type',
                  'value' => [
                    'regexp' => [
                      'pattern' => '/^(1|A)$/',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];
    }

    /**
     * Tests operations.
     *
     * @legacy-covers ::diff
     * @legacy-covers ::intersect
     * @legacy-covers ::merge
     */
    #[DataProvider('providerOperands')]
    public function testOperations(HTMLRestrictions $a, HTMLRestrictions $b, $expected_diff, $expected_intersection, $expected_union): void
    {
        // This looks more complicated than it is: it applies the same processing to
        // all three of the expected operation results.
        foreach (['diff', 'intersection', 'union'] as $op) {
            $parameter = "expected_$op";
            // Ensure that the operation expectation is 'a' or 'b' whenever possible.
            if ($a == $$parameter) {
                throw new \LogicException("List 'a' as the expected $op rather than specifying it in full, to keep the tests legible.");
            } else {
                if ($b == $$parameter) {
                    throw new \LogicException("List 'b' as the expected $op rather than specifying it in full, to keep the tests legible.");
                }
            }
            // Map any expected 'a' or 'b' string value to the corresponding operand.
            if ($$parameter === 'a') {
                $$parameter = $a;
            } elseif ($$parameter === 'b') {
                $$parameter = $b;
            }
            assert($$parameter instanceof HTMLRestrictions);
        }
        $this->assertEquals($expected_diff, $a->diff($b));
        $this->assertEquals($expected_intersection, $a->intersect($b));
        $this->assertEquals($expected_union, $a->merge($b));
    }

    /**
     * Provides data to testOperations().
     */
    public static function providerOperands(): \Generator
    {
        // Empty set operand cases.
        yield 'any set + empty set' => [
          'a' => new HTMLRestrictions(['a' => ['href' => true]]),
          'b' => HTMLRestrictions::emptySet(),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'empty set + any set' => [
          'a' => HTMLRestrictions::emptySet(),
          'b' => new HTMLRestrictions(['a' => ['href' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];

        // Basic cases: tags.
        yield 'union of two very restricted tags' => [
          'a' => new HTMLRestrictions(['a' => false]),
          'b' => new HTMLRestrictions(['a' => false]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'a',
        ];
        yield 'union of two very unrestricted tags' => [
          'a' => new HTMLRestrictions(['a' => true]),
          'b' => new HTMLRestrictions(['a' => true]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'a',
        ];
        yield 'union of one very unrestricted tag with one very restricted tag' => [
          'a' => new HTMLRestrictions(['a' => true]),
          'b' => new HTMLRestrictions(['a' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'union of one very unrestricted tag with one very restricted tag — vice versa' => [
          'a' => new HTMLRestrictions(['a' => false]),
          'b' => new HTMLRestrictions(['a' => true]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];

        // Basic cases: attributes.
        yield 'set + set with empty intersection' => [
          'a' => new HTMLRestrictions(['a' => ['href' => true]]),
          'b' => new HTMLRestrictions(['b' => ['href' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => ['href' => true], 'b' => ['href' => true]]),
        ];
        yield 'set + identical set' => [
          'a' => new HTMLRestrictions(['b' => ['href' => true]]),
          'b' => new HTMLRestrictions(['b' => ['href' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'b',
          'expected_union' => 'b',
        ];
        yield 'set + superset' => [
          'a' => new HTMLRestrictions(['a' => ['href' => true]]),
          'b' => new HTMLRestrictions(['b' => ['href' => true], 'a' => ['href' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];

        // Tag restrictions.
        yield 'tag restrictions are different: <a> vs <b c>' => [
          'a' => new HTMLRestrictions(['a' => false]),
          'b' => new HTMLRestrictions(['b' => ['c' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => false, 'b' => ['c' => true]]),
        ];
        yield 'tag restrictions are different: <a> vs <b c> — vice versa' => [
          'a' => new HTMLRestrictions(['b' => ['c' => true]]),
          'b' => new HTMLRestrictions(['a' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => false, 'b' => ['c' => true]]),
        ];
        yield 'tag restrictions are different: <a *> vs <b c>' => [
          'a' => new HTMLRestrictions(['a' => true]),
          'b' => new HTMLRestrictions(['b' => ['c' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => true, 'b' => ['c' => true]]),
        ];
        yield 'tag restrictions are different: <a *> vs <b c> — vice versa' => [
          'a' => new HTMLRestrictions(['b' => ['c' => true]]),
          'b' => new HTMLRestrictions(['a' => true]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => true, 'b' => ['c' => true]]),
        ];

        // Attribute restrictions.
        yield 'attribute restrictions are less permissive: <a *> vs <a>' => [
          'a' => new HTMLRestrictions(['a' => true]),
          'b' => new HTMLRestrictions(['a' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'attribute restrictions are more permissive: <a> vs <a *>' => [
          'a' => new HTMLRestrictions(['a' => false]),
          'b' => new HTMLRestrictions(['a' => true]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];

        yield 'attribute restrictions are more permissive: <a href> vs <a *>' => [
          'a' => new HTMLRestrictions(['a' => ['href' => true]]),
          'b' => new HTMLRestrictions(['a' => true]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'attribute restrictions are more permissive: <a> vs <a href>' => [
          'a' => new HTMLRestrictions(['a' => false]),
          'b' => new HTMLRestrictions(['a' => ['href' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'attribute restrictions are more restrictive: <a href> vs <a>' => [
          'a' => new HTMLRestrictions(['a' => ['href' => true]]),
          'b' => new HTMLRestrictions(['a' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'attribute restrictions are more restrictive: <a *> vs <a href>' => [
          'a' => new HTMLRestrictions(['a' => true]),
          'b' => new HTMLRestrictions(['a' => ['href' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'attribute restrictions are different: <a href> vs <a hreflang>' => [
          'a' => new HTMLRestrictions(['a' => ['href' => true]]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions(['a' => false]),
          'expected_union' => new HTMLRestrictions(['a' => ['href' => true, 'hreflang' => true]]),
        ];
        yield 'attribute restrictions are different: <a href> vs <a hreflang> — vice versa' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => true]]),
          'b' => new HTMLRestrictions(['a' => ['href' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions(['a' => false]),
          'expected_union' => new HTMLRestrictions(['a' => ['href' => true, 'hreflang' => true]]),
        ];

        // Attribute value restriction.
        yield 'attribute restrictions are different: <a hreflang="en"> vs <a hreflang="fr">' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => ['fr' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions(['a' => false]),
          'expected_union' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true, 'fr' => true]]]),
        ];
        yield 'attribute restrictions are different: <a hreflang="en"> vs <a hreflang="fr"> — vice versa' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => ['fr' => true]]]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions(['a' => false]),
          'expected_union' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true, 'fr' => true]]]),
        ];
        yield 'attribute restrictions are different: <a hreflang=*> vs <a hreflang="en">' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => true]]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'attribute restrictions are different: <a hreflang=*> vs <a hreflang="en"> — vice versa' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'attribute restrictions are different: <ol type=*> vs <ol type="A">' => [
          'a' => new HTMLRestrictions(['ol' => ['type' => true]]),
          'b' => new HTMLRestrictions(['ol' => ['type' => ['A' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'attribute restrictions are different: <ol type=*> vs <ol type="A"> — vice versa' => [
          'a' => new HTMLRestrictions(['ol' => ['type' => ['A' => true]]]),
          'b' => new HTMLRestrictions(['ol' => ['type' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'attribute restrictions are different: <ol type=*> vs <ol type="1">' => [
          'a' => new HTMLRestrictions(['ol' => ['type' => true]]),
          'b' => new HTMLRestrictions(['ol' => ['type' => ['1' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'attribute restrictions are different: <ol type=*> vs <ol type="1"> — vice versa' => [
          'a' => new HTMLRestrictions(['ol' => ['type' => ['1' => true]]]),
          'b' => new HTMLRestrictions(['ol' => ['type' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'attribute restrictions are the same: <ol type="1"> vs <ol type="1">' => [
          'a' => new HTMLRestrictions(['ol' => ['type' => ['1' => true]]]),
          'b' => new HTMLRestrictions(['ol' => ['type' => ['1' => true]]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'a',
        ];

        // Complex cases.
        yield 'attribute restrictions are different: <a hreflang="en"> vs <strong>' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'b' => new HTMLRestrictions(['strong' => true]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]], 'strong' => true]),
        ];
        yield 'attribute restrictions are different: <a hreflang="en"> vs <strong> — vice versa' => [
          'a' => new HTMLRestrictions(['strong' => true]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]], 'strong' => true]),
        ];
        yield 'very restricted tag + slightly restricted tag' => [
          'a' => new HTMLRestrictions(['a' => false]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'very restricted tag + slightly restricted tag — vice versa' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'b' => new HTMLRestrictions(['a' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'very unrestricted tag + slightly restricted tag' => [
          'a' => new HTMLRestrictions(['a' => true]),
          'b' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'very unrestricted tag + slightly restricted tag — vice versa' => [
          'a' => new HTMLRestrictions(['a' => ['hreflang' => ['en' => true]]]),
          'b' => new HTMLRestrictions(['a' => true]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];

        // Wildcard tag + matching tag cases.
        yield 'wildcard + matching tag: attribute intersection — without possible resolving' => [
          'a' => new HTMLRestrictions(['p' => ['class' => true]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['p' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + matching tag: attribute intersection — without possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'b' => new HTMLRestrictions(['p' => ['class' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['p' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + matching tag: attribute intersection — WITH possible resolving' => [
          'a' => new HTMLRestrictions(['p' => ['class' => true]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true], 'p' => false]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => new HTMLRestrictions(['p' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + matching tag: attribute intersection — WITH possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true], 'p' => false]),
          'b' => new HTMLRestrictions(['p' => ['class' => true]]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'expected_intersection' => 'b',
          'expected_union' => new HTMLRestrictions(['p' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + matching tag: attribute value intersection — without possible resolving' => [
          'a' => new HTMLRestrictions(['p' => ['class' => ['text-align-center' => true, 'text-align-justify' => true]]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            'p' => [
              'class' => [
                'text-align-center' => true,
                'text-align-justify' => true,
              ],
            ],
            '$text-container' => ['class' => ['text-align-center' => true]],
          ]),
        ];
        yield 'wildcard + matching tag: attribute value intersection — without possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]]]),
          'b' => new HTMLRestrictions(['p' => ['class' => ['text-align-center' => true, 'text-align-justify' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            'p' => [
              'class' => [
                'text-align-center' => true,
                'text-align-justify' => true,
              ],
            ],
            '$text-container' => ['class' => ['text-align-center' => true]],
          ]),
        ];
        yield 'wildcard + matching tag: attribute value intersection — WITH possible resolving' => [
          'a' => new HTMLRestrictions(['p' => ['class' => ['text-align-center' => true, 'text-align-justify' => true]]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]], 'p' => false]),
          'expected_diff' => new HTMLRestrictions(['p' => ['class' => ['text-align-justify' => true]]]),
          'expected_intersection' => new HTMLRestrictions(['p' => ['class' => ['text-align-center' => true]]]),
          'expected_union' => new HTMLRestrictions([
            'p' => [
              'class' => [
                'text-align-center' => true,
                'text-align-justify' => true,
              ],
            ],
            '$text-container' => ['class' => ['text-align-center' => true]],
          ]),
        ];
        yield 'wildcard + matching tag: attribute value intersection — WITH possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]], 'p' => false]),
          'b' => new HTMLRestrictions(['p' => ['class' => ['text-align-center' => true, 'text-align-justify' => true]]]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]]]),
          'expected_intersection' => new HTMLRestrictions(['p' => ['class' => ['text-align-center' => true]]]),
          'expected_union' => new HTMLRestrictions([
            'p' => [
              'class' => [
                'text-align-center' => true,
                'text-align-justify' => true,
              ],
            ],
            '$text-container' => ['class' => ['text-align-center' => true]],
          ]),
        ];
        yield 'wildcard + matching tag: on both sides' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true, 'foo' => true], 'p' => false]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true], 'p' => false]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['foo' => true], 'p' => ['foo' => true]]),
          'expected_intersection' => new HTMLRestrictions(['$text-container' => ['class' => true], 'p' => ['class' => true]]),
          'expected_union' => 'a',
        ];
        yield 'wildcard + matching tag: on both sides — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true], 'p' => false]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true, 'foo' => true], 'p' => false]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => new HTMLRestrictions(['$text-container' => ['class' => true], 'p' => ['class' => true]]),
          'expected_union' => 'b',
        ];
        yield 'wildcard + matching tag: wildcard resolves into matching tag, but matching tag already supports all attributes' => [
          'a' => new HTMLRestrictions(['p' => true]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => ['foo' => true, 'bar' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            'p' => true,
            '$text-container' => ['class' => ['foo' => true, 'bar' => true]],
          ]),
        ];
        yield 'wildcard + matching tag: wildcard resolves into matching tag, but matching tag already supports all attributes — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => ['foo' => true, 'bar' => true]]]),
          'b' => new HTMLRestrictions(['p' => true]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            'p' => true,
            '$text-container' => ['class' => ['foo' => true, 'bar' => true]],
          ]),
        ];

        // Wildcard tag + non-matching tag cases.
        yield 'wildcard + non-matching tag: attribute diff — without possible resolving' => [
          'a' => new HTMLRestrictions(['span' => ['class' => true]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['span' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + non-matching tag: attribute diff — without possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'b' => new HTMLRestrictions(['span' => ['class' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['span' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + non-matching tag: attribute diff — WITH possible resolving' => [
          'a' => new HTMLRestrictions(['span' => ['class' => true]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true], 'span' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions(['span' => false]),
          'expected_union' => new HTMLRestrictions(['span' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + non-matching tag: attribute diff — WITH possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true], 'span' => false]),
          'b' => new HTMLRestrictions(['span' => ['class' => true]]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'expected_intersection' => new HTMLRestrictions(['span' => false]),
          'expected_union' => new HTMLRestrictions(['span' => ['class' => true], '$text-container' => ['class' => true]]),
        ];
        yield 'wildcard + non-matching tag: attribute value diff — without possible resolving' => [
          'a' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
          ]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => ['vertical-align-top' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
            '$text-container' => ['class' => ['vertical-align-top' => true]],
          ]),
        ];
        yield 'wildcard + non-matching tag: attribute value diff — without possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => ['vertical-align-top' => true]]]),
          'b' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
          ]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
            '$text-container' => ['class' => ['vertical-align-top' => true]],
          ]),
        ];
        yield 'wildcard + non-matching tag: attribute value diff — WITH possible resolving' => [
          'a' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
          ]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => ['vertical-align-top' => true]], 'span' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions(['span' => false]),
          'expected_union' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
            '$text-container' => ['class' => ['vertical-align-top' => true]],
          ]),
        ];
        yield 'wildcard + non-matching tag: attribute value diff — WITH possible resolving — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => ['vertical-align-top' => true]], 'span' => false]),
          'b' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
          ]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['class' => ['vertical-align-top' => true]]]),
          'expected_intersection' => new HTMLRestrictions(['span' => false]),
          'expected_union' => new HTMLRestrictions([
            'span' => [
              'class' => [
                'vertical-align-top' => true,
                'vertical-align-bottom' => true,
              ],
            ],
            '$text-container' => ['class' => ['vertical-align-top' => true]],
          ]),
        ];

        // Wildcard tag + wildcard tag cases.
        yield 'wildcard + wildcard tag: attributes' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true, 'foo' => true]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['foo' => true]]),
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'wildcard + wildcard tag: attributes — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true, 'foo' => true]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'wildcard + wildcard tag: attribute values' => [
          'a' => new HTMLRestrictions([
            '$text-container' => [
              'class' => [
                'text-align-center' => true,
                'text-align-justify' => true,
              ],
            ],
          ]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]]]),
          'expected_diff' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-justify' => true]]]),
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'wildcard + wildcard tag: attribute values — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => ['text-align-center' => true]]]),
          'b' => new HTMLRestrictions([
            '$text-container' => [
              'class' => [
                'text-align-center' => true,
                'text-align-justify' => true,
              ],
            ],
          ]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];

        // Concrete attributes + wildcard attribute cases for all 3 possible
        // wildcard locations. Parametrized to prevent excessive repetition and
        // subtle differences.
        $wildcard_locations = [
          'prefix' => 'data-*',
          'infix' => '*-entity-*',
          'suffix' => '*-type',
        ];
        foreach ($wildcard_locations as $wildcard_location => $wildcard_attr_name) {
            yield "concrete attrs + wildcard $wildcard_location attr that covers a superset" => [
              'a' => new HTMLRestrictions(['img' => ['data-entity-bundle-type' => true, 'data-entity-type' => true]]),
              'b' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true]]),
              'expected_diff' => HTMLRestrictions::emptySet(),
              'expected_intersection' => 'a',
              'expected_union' => 'b',
            ];
            yield "concrete attrs + wildcard $wildcard_location attr that covers a superset — vice versa" => [
              'a' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true]]),
              'b' => new HTMLRestrictions(['img' => ['data-entity-bundle-type' => true, 'data-entity-type' => true]]),
              'expected_diff' => 'a',
              'expected_intersection' => 'b',
              'expected_union' => 'a',
            ];
            yield "concrete attrs + wildcard $wildcard_location attr that covers a subset" => [
              'a' => new HTMLRestrictions([
                'img' => [
                  'data-entity-bundle-type' => true,
                  'data-entity-type' => true,
                  'class' => true,
                ],
              ]),
              'b' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true]]),
              'expected_diff' => new HTMLRestrictions(['img' => ['class' => true]]),
              'expected_intersection' => new HTMLRestrictions([
                'img' => [
                  'data-entity-bundle-type' => true,
                  'data-entity-type' => true,
                ],
              ]),
              'expected_union' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true, 'class' => true]]),
            ];
            yield "concrete attrs + wildcard $wildcard_location attr that covers a subset — vice versa" => [
              'a' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true]]),
              'b' => new HTMLRestrictions([
                'img' => [
                  'data-entity-bundle-type' => true,
                  'data-entity-type' => true,
                  'class' => true,
                ],
              ]),
              'expected_diff' => 'a',
              'expected_intersection' => new HTMLRestrictions([
                'img' => [
                  'data-entity-bundle-type' => true,
                  'data-entity-type' => true,
                ],
              ]),
              'expected_union' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true, 'class' => true]]),
            ];
            yield "wildcard $wildcard_location attr + wildcard $wildcard_location attr" => [
              'a' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true, 'class' => true]]),
              'b' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true]]),
              'expected_diff' => new HTMLRestrictions(['img' => ['class' => true]]),
              'expected_intersection' => 'b',
              'expected_union' => 'a',
            ];
            yield "wildcard $wildcard_location attr + wildcard $wildcard_location attr — vice versa" => [
              'a' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true]]),
              'b' => new HTMLRestrictions(['img' => [$wildcard_attr_name => true, 'class' => true]]),
              'expected_diff' => HTMLRestrictions::emptySet(),
              'expected_intersection' => 'a',
              'expected_union' => 'b',
            ];
        }

        // Global attribute `*` HTML tag + global attribute `*` HTML tag cases.
        yield 'global attribute tag + global attribute tag: no overlap in attributes' => [
          'a' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'b' => new HTMLRestrictions(['*' => ['baz' => false]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false, 'baz' => false]]),
        ];
        yield 'global attribute tag + global attribute tag: no overlap in attributes — vice versa' => [
          'a' => new HTMLRestrictions(['*' => ['baz' => false]]),
          'b' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false, 'baz' => false]]),
        ];
        yield 'global attribute tag + global attribute tag: overlap in attributes, same attribute value restrictions' => [
          'a' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          'b' => new HTMLRestrictions(['*' => ['bar' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          'expected_diff' => new HTMLRestrictions(['*' => ['foo' => true]]),
          'expected_intersection' => 'b',
          'expected_union' => 'a',
        ];
        yield 'global attribute tag + global attribute tag: overlap in attributes, same attribute value restrictions — vice versa' => [
          'a' => new HTMLRestrictions(['*' => ['bar' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          'b' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          'expected_diff' => HTMLRestrictions::emptySet(),
          'expected_intersection' => 'a',
          'expected_union' => 'b',
        ];
        yield 'global attribute tag + global attribute tag: overlap in attributes, different attribute value restrictions' => [
          'a' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          'b' => new HTMLRestrictions(['*' => ['bar' => true, 'dir' => true, 'foo' => false]]),
          'expected_diff' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'expected_intersection' => new HTMLRestrictions([
            '*' => [
              'bar' => false,
              'dir' => ['ltr' => true, 'rtl' => true],
              'foo' => false,
            ],
          ]),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => true, 'dir' => true]]),
        ];
        yield 'global attribute tag + global attribute tag: overlap in attributes, different attribute value restrictions — vice versa' => [
          'a' => new HTMLRestrictions(['*' => ['bar' => true, 'dir' => true, 'foo' => false]]),
          'b' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          'expected_diff' => 'a',
          'expected_intersection' => new HTMLRestrictions([
            '*' => [
              'bar' => false,
              'dir' => ['ltr' => true, 'rtl' => true],
              'foo' => false,
            ],
          ]),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => true, 'dir' => true]]),
        ];

        // Global attribute `*` HTML tag + concrete tag.
        yield 'global attribute tag + concrete tag' => [
          'a' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'b' => new HTMLRestrictions(['p' => false]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false], 'p' => false]),
        ];
        yield 'global attribute tag + concrete tag — vice versa' => [
          'a' => new HTMLRestrictions(['p' => false]),
          'b' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false], 'p' => false]),
        ];
        yield 'global attribute tag + concrete tag with allowed attribute' => [
          'a' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'b' => new HTMLRestrictions(['p' => ['baz' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false], 'p' => ['baz' => true]]),
        ];
        yield 'global attribute tag + concrete tag with allowed attribute — vice versa' => [
          'a' => new HTMLRestrictions(['p' => ['baz' => true]]),
          'b' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false], 'p' => ['baz' => true]]),
        ];

        // Global attribute `*` HTML tag + wildcard tag.
        yield 'global attribute tag + wildcard tag' => [
          'a' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'b' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            '*' => [
              'foo' => true,
              'bar' => false,
            ],
            '$text-container' => ['class' => true],
          ]),
        ];
        yield 'global attribute tag + wildcard tag — vice versa' => [
          'a' => new HTMLRestrictions(['$text-container' => ['class' => true]]),
          'b' => new HTMLRestrictions(['*' => ['foo' => true, 'bar' => false]]),
          'expected_diff' => 'a',
          'expected_intersection' => HTMLRestrictions::emptySet(),
          'expected_union' => new HTMLRestrictions([
            '*' => [
              'foo' => true,
              'bar' => false,
            ],
            '$text-container' => ['class' => true],
          ]),
        ];
    }

    /**
     * Tests subsets.
     *
     * @legacy-covers ::getWildcardSubset
     * @legacy-covers ::getConcreteSubset
     * @legacy-covers ::getPlainTagsSubset
     * @legacy-covers ::extractPlainTagsSubset
     */
    #[DataProvider('providerSubsets')]
    public function testSubsets(HTMLRestrictions $input, HTMLRestrictions $expected_wildcard_subset, HTMLRestrictions $expected_concrete_subset, HTMLRestrictions $expected_plain_tags_subset, HTMLRestrictions $expected_extracted_plain_tags_subset): void
    {
        $this->assertEquals($expected_wildcard_subset, $input->getWildcardSubset());
        $this->assertEquals($expected_concrete_subset, $input->getConcreteSubset());
        $this->assertEquals($expected_plain_tags_subset, $input->getPlainTagsSubset());
        $this->assertEquals($expected_extracted_plain_tags_subset, $input->extractPlainTagsSubset());
    }

    /**
     * Provides data to testSubsets().
     */
    public static function providerSubsets(): \Generator
    {
        yield 'empty set' => [
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
        ];

        yield 'without wildcards' => [
          new HTMLRestrictions(['div' => false]),
          new HTMLRestrictions([]),
          new HTMLRestrictions(['div' => false]),
          new HTMLRestrictions(['div' => false]),
          new HTMLRestrictions(['div' => false]),
        ];

        yield 'without wildcards with attributes' => [
          new HTMLRestrictions(['div' => ['foo' => ['bar' => true]]]),
          new HTMLRestrictions([]),
          new HTMLRestrictions(['div' => ['foo' => ['bar' => true]]]),
          new HTMLRestrictions([]),
          new HTMLRestrictions(['div' => false]),
        ];

        yield 'with wildcards' => [
          new HTMLRestrictions([
            'div' => false,
            '$text-container' => ['data-llama' => true],
            '*' => ['on*' => false, 'dir' => ['ltr' => true, 'rtl' => true]],
          ]),
          new HTMLRestrictions(['$text-container' => ['data-llama' => true]]),
          new HTMLRestrictions(['div' => false, '*' => ['on*' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          new HTMLRestrictions(['div' => false]),
          new HTMLRestrictions(['div' => false]),
        ];

        yield 'wildcards and global attribute tag' => [
          new HTMLRestrictions([
            '$text-container' => ['data-llama' => true],
            '*' => ['on*' => false, 'dir' => ['ltr' => true, 'rtl' => true]],
          ]),
          new HTMLRestrictions(['$text-container' => ['data-llama' => true]]),
          new HTMLRestrictions(['*' => ['on*' => false, 'dir' => ['ltr' => true, 'rtl' => true]]]),
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
        ];

        yield 'only wildcards' => [
          new HTMLRestrictions(['$text-container' => ['data-llama' => true]]),
          new HTMLRestrictions(['$text-container' => ['data-llama' => true]]),
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
          new HTMLRestrictions([]),
        ];
    }

}
