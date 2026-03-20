<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\views\filter;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\views\Plugin\views\filter\NumericFilter.
 */
#[CoversClass(NumericFilter::class)]
#[Group('Views')]
class NumericFilterTest extends UnitTestCase
{
    /**
     * Tests the acceptExposedInput method.
     */
    #[DataProvider('provideAcceptExposedInput')]
    public function testAcceptExposedInput($options, $value, $expected): void
    {
        $plugin_definition = [
          'title' => $this->randomMachineName(),
        ];

        $plugin = new NumericFilter([], 'numeric', $plugin_definition);
        $translation_stub = $this->getStringTranslationStub();
        $plugin->setStringTranslation($translation_stub);

        $view = $this->prophesize(ViewExecutable::class)->reveal();
        $display = $this->prophesize(DisplayPluginBase::class)->reveal();
        $view->display_handler = $display;
        $plugin->init($view, $view->display_handler, $options);

        $this->assertSame($expected, $plugin->acceptExposedInput($value));
    }

    /**
     * Data provider for testAcceptExposedInput test.
     *
     * @return array[]
     *   The test cases.
     */
    public static function provideAcceptExposedInput(): array
    {
        // [$options, $value, $expected]
        return [
          // Not exposed by default. Bypass parsing and return true.
          'defaults' => [[], [], true],
          'exposed but not configured' => [
            [
              'exposed' => true,
              'expose' => [],
              'group_info' => [],
            ],
            [],
            false,
          ],
          // Exposed but not grouped.
          'exposed not grouped - missing value' => [
            [
              'exposed' => true,
              'expose' => ['identifier' => 'test_id'],
            ],
            [],
            true,
          ],
          'exposed not grouped - wrong group config' => [
            [
              'exposed' => true,
              'group_info' => ['identifier' => 'test_id'],
            ],
            ['test_id' => ['value' => 1]],
            // Wrong identifier configured.
            false,
          ],
          'exposed not grouped' => [
            [
              'exposed' => true,
              'expose' => ['identifier' => 'test_id'],
            ],
            ['test_id' => ['value' => 1]],
            true,
          ],
          // Exposed and grouped.
          'exposed grouped - missing value' => [
            [
              'exposed' => true,
              'is_grouped' => true,
              'group_info' => ['identifier' => 'test_id'],
            ],
            [],
            true,
          ],
          'exposed grouped - wrong group config' => [
            [
              'exposed' => true,
              'is_grouped' => true,
              'expose' => ['identifier' => 'test_id'],
            ],
            ['test_id' => ['value' => 1]],
            false,
          ],
          'exposed grouped' => [
            [
              'exposed' => true,
              'is_grouped' => true,
              'group_info' => ['identifier' => 'test_id'],
            ],
            ['test_id' => ['value' => 1]],
            true,
          ],
        ];
    }

}
