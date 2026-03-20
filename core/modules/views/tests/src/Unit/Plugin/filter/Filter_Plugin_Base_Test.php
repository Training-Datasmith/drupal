<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\filter;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\filter\FilterPluginBase.
 */
#[CoversClass(FilterPluginBase::class)]
#[Group('views')]
class FilterPluginBaseTest extends UnitTestCase
{
    /**
     * Tests accept exposed input.
     */
    #[DataProvider('acceptExposedInputProvider')]
    public function testAcceptExposedInput(bool $expected_result, array $options, array $input): void
    {
        $definition = [
          'title' => 'Accept exposed input Test',
          'group' => 'Test',
        ];
        $filter = new FilterPluginBaseStub([], 'stub', $definition);
        $filter->options = $options;
        $this->assertSame($expected_result, $filter->acceptExposedInput($input));
    }

    /**
     * The data provider for testAcceptExposedInput.
     *
     * @return array
     *   The data set.
     */
    public static function acceptExposedInputProvider()
    {
        return [
          'not-exposed' => [true, ['exposed' => false], []],
          'exposed-no-input' => [true, ['exposed' => true], []],
          'exposed-zero-input' => [false, [
            'exposed' => true,
            'is_grouped' => false,
            'expose' => [
              'use_operator' => true,
              'operator_id' => '=',
              'identifier' => 'identifier',
            ],
          ], ['identifier' => 0],
          ],
          'exposed-empty-array-input' => [false, [
            'exposed' => true,
            'is_grouped' => false,
            'expose' => [
              'use_operator' => true,
              'operator_id' => '=',
              'identifier' => 'identifier',
            ],
          ], ['identifier' => []],
          ],
        ];
    }

}

/**
 * Empty class to support testing filter plugins.
 */
class FilterPluginBaseStub extends FilterPluginBase
{
}
