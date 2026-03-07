<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\Bytes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests bytes size parsing helper methods.
 */
#[CoversClass(Bytes::class)]
#[Group('Utility')]
class BytesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Tests \Drupal\Component\Utility\Bytes::toNumber().
     *
     * @param string $size
     *   The value for the size argument for
     *   \Drupal\Component\Utility\Bytes::toNumber().
     * @param float $expected_number
     *   The expected return value from
     *   \Drupal\Component\Utility\Bytes::toNumber().
     */
    #[DataProvider('providerTestToNumber')]
    public function testToNumber($size, float $expected_number): void
    {
        $this->assertSame($expected_number, Bytes::toNumber($size));
    }

    /**
     * Provides data for testToNumber().
     *
     * @return array
     *   An array of arrays, each containing the argument for
     *   \Drupal\Component\Utility\Bytes::toNumber(): size, and the expected
     *   return value with the expected type (float).
     */
    public static function providerTestToNumber(): array
    {
        return [
          ['1', 1.0],
          ['1 byte', 1.0],
          ['1 KB'  , (float) Bytes::KILOBYTE],
          ['1 MB'  , (float) pow(Bytes::KILOBYTE, 2)],
          ['1 GB'  , (float) pow(Bytes::KILOBYTE, 3)],
          ['1 TB'  , (float) pow(Bytes::KILOBYTE, 4)],
          ['1 PB'  , (float) pow(Bytes::KILOBYTE, 5)],
          ['1 EB'  , (float) pow(Bytes::KILOBYTE, 6)],
          // Zettabytes and yottabytes cannot be represented by integers on 64-bit
          // systems, so pow() returns a float.
          ['1 ZB'  , pow(Bytes::KILOBYTE, 7)],
          ['1 YB'  , pow(Bytes::KILOBYTE, 8)],
          ['23476892 bytes', 23476892.0],
          // 76 MB.
          ['76MRandomStringThatShouldBeIgnoredByParseSize.', 79691776.0],
          // cspell:ignore giggabyte
          // 76.24 GB (with typo).
          ['76.24 Giggabyte', 81862076662.0],
          ['1.5', 2.0],
          [1.5, 2.0],
          ['2.4', 2.0],
          [2.4, 2.0],
          ['', 0.0],
          ['9223372036854775807', 9223372036854775807.0],
        ];
    }

    /**
     * Tests \Drupal\Component\Utility\Bytes::validate().
     *
     * @param string $string
     *   The value for the string argument for
     *   \Drupal\Component\Utility\Bytes::validate().
     * @param bool $expected_result
     *   The expected return value from
     *   \Drupal\Component\Utility\Bytes::validate().
     *
     * @legacy-covers ::validate
     * @legacy-covers ::validateConstraint
     */
    #[DataProvider('providerTestValidate')]
    public function testValidate($string, bool $expected_result): void
    {
        $this->assertSame($expected_result, Bytes::validate($string));

        $execution_context = $this->prophesize(ExecutionContextInterface::class);
        if ($expected_result) {
            $execution_context->addViolation(Argument::cetera())
              ->shouldNotBeCalled();
        } else {
            $execution_context->addViolation(Argument::cetera())
              ->shouldBeCalledTimes(1);
        }
        Bytes::validateConstraint($string, $execution_context->reveal());
    }

    /**
     * Provides data for testValidate().
     *
     * @return array
     *   An array of arrays, each containing the argument for
     *   \Drupal\Component\Utility\Bytes::validate(): string, and the expected
     *   return value with the expected type (bool).
     */
    public static function providerTestValidate(): array
    {
        return [
          // String not starting with a number.
          ['foo', false],
          ['fifty megabytes', false],
          ['five', false],
          // Test spaces and capital combinations.
          [5, true],
          ['5M', true],
          ['5m', true],
          ['5 M', true],
          ['5 m', true],
          ['5Mb', true],
          ['5mb', true],
          ['5 Mb', true],
          ['5 mb', true],
          ['5Gb', true],
          ['5gb', true],
          ['5 Gb', true],
          ['5 gb', true],
          // Test all allowed suffixes.
          ['5', true],
          ['5 b', true],
          ['5 byte', true],
          ['5 bytes', true],
          ['5 k', true],
          ['5 kb', true],
          ['5 kilobyte', true],
          ['5 kilobytes', true],
          ['5 m', true],
          ['5 mb', true],
          ['5 megabyte', true],
          ['5 megabytes', true],
          ['5 g', true],
          ['5 gb', true],
          ['5 gigabyte', true],
          ['5 gigabytes', true],
          ['5 t', true],
          ['5 tb', true],
          ['5 terabyte', true],
          ['5 terabytes', true],
          ['5 p', true],
          ['5 pb', true],
          ['5 petabyte', true],
          ['5 petabytes', true],
          ['5 e', true],
          ['5 eb', true],
          ['5 exabyte', true],
          ['5 exabytes', true],
          ['5 z', true],
          ['5 zb', true],
          ['5 zettabyte', true],
          ['5 zettabytes', true],
          ['5 y', true],
          ['5 yb', true],
          ['5 yottabyte', true],
          ['5 yottabytes', true],
          // Test with decimal.
          [5.1, true],
          ['5.1M', true],
          ['5.1mb', true],
          ['5.1 M', true],
          ['5.1 Mb', true],
          ['5.1 megabytes', true],
          // Test with an unauthorized string.
          ['1five', false],
          ['1 1 byte', false],
          ['1,1 byte', false],
          // Test with leading and trailing spaces.
          [' 5.1mb', false],
          ['5.1mb ', true],
          [' 5.1mb ', false],
          [' 5.1 megabytes', false],
          ['5.1 megabytes ', true],
          [' 5.1 megabytes ', false],
          ['300 0', false],
        ];
    }

}
