<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit\process;

use Drupal\migrate\Plugin\migrate\process\DefaultValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the default_value process plugin.
 */
#[CoversClass(DefaultValue::class)]
#[Group('migrate')]
class DefaultValueTest extends MigrateProcessTestCase
{
    /**
     * Tests the default_value process plugin.
     *
     * @legacy-covers ::transform
     */
    #[DataProvider('defaultValueDataProvider')]
    public function testDefaultValue($configuration, $expected_value, $value): void
    {
        $process = new DefaultValue($configuration, 'default_value', []);
        $value = $process->transform($value, $this->migrateExecutable, $this->row, 'destination_property');
        $this->assertSame($expected_value, $value);
    }

    /**
     * Provides data for the successful lookup test.
     *
     * @return array
     *   An array of test cases.
     */
    public static function defaultValueDataProvider()
    {
        return [
          'strict_true_value_populated_array' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => [0, 1, 2],
            'value' => [0, 1, 2],
          ],
          'strict_true_value_empty_string' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => '',
            'value' => '',
          ],
          'strict_true_value_false' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => false,
            'value' => false,
          ],
          'strict_true_value_null' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => null,
          ],
          'strict_true_value_zero_string' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => '0',
            'value' => '0',
          ],
          'strict_true_value_zero' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => 0,
            'value' => 0,
          ],
          'strict_true_value_empty_array' => [
            'configuration' => [
              'strict' => true,
              'default_value' => 1,
            ],
            'expected_value' => [],
            'value' => [],
          ],
          'array_populated' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => [0, 1, 2],
            'value' => [0, 1, 2],
          ],
          'empty_string' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => '',
          ],
          'false' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => false,
          ],
          'null' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => null,
          ],
          'string_zero' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => '0',
          ],
          'int_zero' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => 0,
          ],
          'empty_array' => [
            'configuration' => [
              'default_value' => 1,
            ],
            'expected_value' => 1,
            'value' => [],
          ],
        ];
    }

}
