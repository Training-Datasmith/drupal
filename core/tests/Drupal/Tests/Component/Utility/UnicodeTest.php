<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\Unicode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Test unicode handling features implemented in Unicode component.
 */
#[CoversClass(Unicode::class)]
#[Group('Utility')]
class UnicodeTest extends TestCase
{
    /**
     * Tests multibyte ucfirst.
     */
    #[DataProvider('providerUcfirst')]
    public function testUcfirst($text, $expected): void
    {
        $this->assertEquals($expected, Unicode::ucfirst($text));
    }

    /**
     * Data provider for testUcfirst().
     *
     * @see testUcfirst()
     *
     * @return array
     *   An array containing a string and its uppercase first version.
     */
    public static function providerUcfirst(): array
    {
        // cSpell:disable
        return [
          ['tHe QUIcK bRoWn', 'THe QUIcK bRoWn'],
          ['françAIS', 'FrançAIS'],
          ['über', 'Über'],
          ['åwesome', 'Åwesome'],
          // A multibyte string.
          ['σion', 'Σion'],
        ];
        // cSpell:enable
    }

    /**
     * Tests multibyte lcfirst.
     */
    #[DataProvider('providerLcfirst')]
    public function testLcfirst($text, $expected): void
    {
        $this->assertEquals($expected, Unicode::lcfirst($text));
    }

    /**
     * Data provider for testLcfirst().
     *
     * @see testLcfirst()
     *
     * @return array
     *   An array containing a string and its lowercase version.
     */
    public static function providerLcfirst(): array
    {
        // cSpell:disable
        return [
          ['tHe QUIcK bRoWn', 'tHe QUIcK bRoWn'],
          ['FrançAIS is ÜBER-åwesome', 'françAIS is ÜBER-åwesome'],
          ['Über', 'über'],
          ['Åwesome', 'åwesome'],
          // Add a multibyte string.
          ['ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ', 'αΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ'],
        ];
        // cSpell:enable
    }

    /**
     * Tests multibyte ucwords.
     */
    #[DataProvider('providerUcwords')]
    public function testUcwords($text, $expected): void
    {
        $this->assertEquals($expected, Unicode::ucwords($text));
    }

    /**
     * Data provider for testUcwords().
     *
     * @see testUcwords()
     *
     * @return array
     *   An array containing a string and its capitalized version.
     */
    public static function providerUcwords(): array
    {
        // cSpell:disable
        return [
          ['tHe QUIcK bRoWn', 'THe QUIcK BRoWn'],
          ['françAIS', 'FrançAIS'],
          ['über', 'Über'],
          ['åwesome', 'Åwesome'],
          // Make sure we don't mangle extra spaces.
          ['frànçAIS is  über-åwesome', 'FrànçAIS Is  Über-Åwesome'],
          // Add a multibyte string.
          ['σion', 'Σion'],
        ];
        // cSpell:enable
    }

    /**
     * Tests multibyte truncate.
     */
    #[DataProvider('providerTruncate')]
    public function testTruncate($text, $max_length, $expected, $wordsafe = false, $add_ellipsis = false): void
    {
        $this->assertEquals($expected, Unicode::truncate($text, $max_length, $wordsafe, $add_ellipsis));
    }

    /**
     * Data provider for testTruncate().
     *
     * @see testTruncate()
     *
     * @return array
     *   An array containing:
     *     - The string to test.
     *     - The max length to truncate this string to.
     *     - The expected string result.
     *     - (optional) Boolean for the $wordsafe flag. Defaults to FALSE.
     *     - (optional) Boolean for the $add_ellipsis flag. Defaults to FALSE.
     */
    public static function providerTruncate(): array
    {
        // cSpell:disable
        $tests = [
          ['frànçAIS is über-åwesome', 24, 'frànçAIS is über-åwesome'],
          ['frànçAIS is über-åwesome', 23, 'frànçAIS is über-åwesom'],
          ['frànçAIS is über-åwesome', 17, 'frànçAIS is über-'],
          ['以呂波耳・ほへとち。リヌルヲ。', 6, '以呂波耳・ほ'],
          ['frànçAIS is über-åwesome', 24, 'frànçAIS is über-åwesome', false, true],
          ['frànçAIS is über-åwesome', 23, 'frànçAIS is über-åweso…', false, true],
          ['frànçAIS is über-åwesome', 17, 'frànçAIS is über…', false, true],
          ['123', 1, '…', true, true],
          ['123', 2, '1…', true, true],
          ['123', 3, '123', true, true],
          ['1234', 3, '12…', true, true],
          ['1234567890', 10, '1234567890', true, true],
          ['12345678901', 10, '123456789…', true, true],
          ['12345678901', 11, '12345678901', true, true],
          ['123456789012', 11, '1234567890…', true, true],
          ['12345 7890', 10, '12345 7890', true, true],
          ['12345 7890', 9, '12345…', true, true],
          ['123 567 90', 10, '123 567 90', true, true],
          ['123 567 901', 10, '123 567…', true, true],
          ['Stop. Hammertime.', 17, 'Stop. Hammertime.', true, true],
          ['Stop. Hammertime.', 16, 'Stop…', true, true],
          ['frànçAIS is über-åwesome', 24, 'frànçAIS is über-åwesome', true, true],
          ['frànçAIS is über-åwesome', 23, 'frànçAIS is über…', true, true],
          ['frànçAIS is über-åwesome', 17, 'frànçAIS is über…', true, true],
          ['¿Dónde está el niño?', 20, '¿Dónde está el niño?', true, true],
          ['¿Dónde está el niño?', 19, '¿Dónde está el…', true, true],
          ['¿Dónde está el niño?', 13, '¿Dónde está…', true, true],
          ['¿Dónde está el niño?', 10, '¿Dónde…', true, true],
          ['Help! Help! Help!', 17, 'Help! Help! Help!', true, true],
          ['Help! Help! Help!', 16, 'Help! Help!…', true, true],
          ['Help! Help! Help!', 15, 'Help! Help!…', true, true],
          ['Help! Help! Help!', 14, 'Help! Help!…', true, true],
          ['Help! Help! Help!', 13, 'Help! Help!…', true, true],
          ['Help! Help! Help!', 12, 'Help! Help!…', true, true],
          ['Help! Help! Help!', 11, 'Help! Help…', true, true],
          ['Help! Help! Help!', 10, 'Help!…', true, true],
          ['Help! Help! Help!', 9, 'Help!…', true, true],
          ['Help! Help! Help!', 8, 'Help!…', true, true],
          ['Help! Help! Help!', 7, 'Help!…', true, true],
          ['Help! Help! Help!', 6, 'Help!…', true, true],
          ['Help! Help! Help!', 5, 'Help…', true, true],
          ['Help! Help! Help!', 4, 'Hel…', true, true],
          ['Help! Help! Help!', 3, 'He…', true, true],
          ['Help! Help! Help!', 2, 'H…', true, true],
        ];
        // cSpell:enable

        // Test truncate on text with multiple lines.
        $multi_line = <<<EOF
This is a text that spans multiple lines.
Line 2 goes here.
EOF;
        $multi_line_wordsafe = <<<EOF
This is a text that spans multiple lines.
Line 2
EOF;
        $multi_line_non_wordsafe = <<<EOF
This is a text that spans multiple lines.
Line 2 go
EOF;
        $tests[] = [$multi_line, 51, $multi_line_wordsafe, true];
        $tests[] = [$multi_line, 51, $multi_line_non_wordsafe, false];

        return $tests;
    }

    /**
     * Tests multibyte truncate bytes.
     *
     * @param string $text
     *   The string to truncate.
     * @param int $max_length
     *   The upper limit on the returned string length.
     * @param string $expected
     *   The expected return from Unicode::truncateBytes().
     */
    #[DataProvider('providerTestTruncateBytes')]
    public function testTruncateBytes($text, $max_length, $expected): void
    {
        $this->assertEquals($expected, Unicode::truncateBytes($text, $max_length), 'The string was not correctly truncated.');
    }

    /**
     * Provides data for self::testTruncateBytes().
     *
     * @return array
     *   An array of arrays, each containing the parameters to
     *   self::testTruncateBytes().
     */
    public static function providerTestTruncateBytes(): array
    {
        return [
          // String shorter than max length.
          ['Short string', 42, 'Short string'],
          // Simple string longer than max length.
          ['Longer string than previous.', 10, 'Longer str'],
          // Unicode.
          ['以呂波耳・ほへとち。リヌルヲ。', 10, '以呂波'],
        ];
    }

    /**
     * Tests UTF-8 validation.
     *
     * @param string $text
     *   The text to validate.
     * @param bool $expected
     *   The expected return value from Unicode::validateUtf8().
     * @param string $message
     *   The message to display on failure.
     */
    #[DataProvider('providerTestValidateUtf8')]
    public function testValidateUtf8($text, $expected, $message): void
    {
        $this->assertEquals($expected, Unicode::validateUtf8($text), $message);
    }

    /**
     * Provides data for self::testValidateUtf8().
     *
     * Invalid UTF-8 examples sourced from
     * http://stackoverflow.com/a/11709412/109119.
     *
     * @return array
     *   An array of arrays, each containing the parameters for
     *   self::testValidateUtf8().
     */
    public static function providerTestValidateUtf8(): array
    {
        return [
          // Empty string.
          ['', true, 'An empty string did not validate.'],
          // Simple text string.
          ['Simple text.', true, 'A simple ASCII text string did not validate.'],
          // Invalid UTF-8, overlong 5 byte encoding.
          [chr(0xF8) . chr(0x80) . chr(0x80) . chr(0x80) . chr(0x80), false, 'Invalid UTF-8 was validated.'],
          // High code-point without trailing characters.
          [chr(0xD0) . chr(0x01), false, 'Invalid UTF-8 was validated.'],
        ];
    }

    /**
     * Tests UTF-8 conversion.
     *
     * @param string $data
     *   The data to be converted.
     * @param string $encoding
     *   The encoding the data is in.
     * @param string|bool $expected
     *   The expected result.
     */
    #[DataProvider('providerTestConvertToUtf8')]
    public function testConvertToUtf8($data, $encoding, $expected): void
    {
        $this->assertEquals($expected, Unicode::convertToUtf8($data, $encoding));
    }

    /**
     * Provides data to self::testConvertToUtf8().
     *
     * @return array
     *   An array of arrays, each containing the parameters to
     *   self::testConvertUtf8().  }
     */
    public static function providerTestConvertToUtf8(): array
    {
        return [
          [chr(0x97), 'Windows-1252', '—'],
          [chr(0x99), 'Windows-1252', '™'],
          [chr(0x80), 'Windows-1252', '€'],
        ];
    }

}
