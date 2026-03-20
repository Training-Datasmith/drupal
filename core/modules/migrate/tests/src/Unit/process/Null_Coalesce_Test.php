<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\process\NullCoalesce;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the null_coalesce process plugin.
 */
#[CoversClass(NullCoalesce::class)]
#[Group('migrate')]
class NullCoalesceTest extends MigrateProcessTestCase
{
    /**
     * Tests that an exception is thrown for a non-array value.
     *
     * @legacy-covers ::transform
     */
    public function testExceptionOnInvalidValue(): void
    {
        $this->expectException(MigrateException::class);
        (new NullCoalesce([], 'null_coalesce', []))->transform('invalid', $this->migrateExecutable, $this->row, 'destination_property');
    }

    /**
     * Tests null_coalesce.
     *
     * @param array $source
     *   The source value.
     * @param mixed $expected_result
     *   The expected result.
     *
     * @throws \Drupal\migrate\MigrateException
     */
    #[DataProvider('transformDataProvider')]
    public function testTransform(array $source, $expected_result): void
    {
        $plugin = new NullCoalesce([], 'null_coalesce', []);
        $result = $plugin->transform($source, $this->migrateExecutable, $this->row, 'destination_property');
        $this->assertSame($expected_result, $result);
    }

    /**
     * Provides Data for ::testTransform.
     */
    public static function transformDataProvider()
    {
        return [
          'all null' => [
            'source' => [null, null, null],
            'expected_result' => null,
          ],
          'false first' => [
            'source' => [false, null, null],
            'expected_result' => false,
          ],
          'no null' => [
            'source' => ['test', 'test2'],
            'expected_result' => 'test',
          ],
          'string first' => [
            'source' => ['test', null, 'test2'],
            'expected_result' => 'test',
          ],
          'empty string' => [
            'source' => [null, '', null],
            'expected_result' => '',
          ],
          'array' => [
            'source' => [null, null, [1, 2, 3]],
            'expected_result' => [1, 2, 3],
          ],
        ];
    }

    /**
     * Tests null_coalesce.
     *
     * @param array $source
     *   The source value.
     * @param string $default_value
     *   The default value.
     * @param mixed $expected_result
     *   The expected result.
     *
     * @throws \Drupal\migrate\MigrateException
     */
    #[DataProvider('transformWithDefaultProvider')]
    public function testTransformWithDefault(array $source, $default_value, $expected_result): void
    {
        $plugin = new NullCoalesce(['default_value' => $default_value], 'null_coalesce', []);
        $result = $plugin->transform($source, $this->migrateExecutable, $this->row, 'destination_property');
        $this->assertSame($expected_result, $result);
    }

    /**
     * Provides Data for ::testTransformWithDefault.
     */
    public static function transformWithDefaultProvider()
    {
        return [
          'default not used' => [
            'source' => [null, null, 'Test', 'Test 2'],
            'default_value' => 'default',
            'expected_result' => 'Test',
          ],
          'default string' => [
            'source' => [null, null],
            'default_value' => 'default',
            'expected_result' => 'default',
          ],
          'default NULL' => [
            'source' => [null, null],
            'default_value' => null,
            'expected_result' => null,
          ],
        ];
    }

}
