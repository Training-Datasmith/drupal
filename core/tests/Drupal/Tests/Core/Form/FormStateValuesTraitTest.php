<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Form\FormStateValuesTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Form\FormStateValuesTrait.
 */
#[CoversClass(FormStateValuesTrait::class)]
#[Group('Form')]
class FormStateValuesTraitTest extends UnitTestCase
{
    /**
     * Tests that setting the value for an element adds to the values.
     */
    public function testSetValueForElement(): void
    {
        $element = [
          '#parents' => [
            'foo',
            'bar',
          ],
        ];
        $value = $this->randomMachineName();

        $form_state = new FormStateValuesTraitStub();
        $form_state->setValueForElement($element, $value);
        $expected = [
          'foo' => [
            'bar' => $value,
          ],
        ];
        $this->assertSame($expected, $form_state->getValues());
    }

    /**
     * Tests get value.
     */
    #[DataProvider('providerGetValue')]
    public function testGetValue($key, $expected, $default = null): void
    {
        $form_state = (new FormStateValuesTraitStub())->setValues([
          'foo' => 'one',
          'bar' => [
            'baz' => 'two',
          ],
        ]);
        $this->assertSame($expected, $form_state->getValue($key, $default));
    }

    /**
     * Provides data to self::testGetValue().
     *
     * @return array[]
     *   Items are arrays of two items:
     *   - The key for which to get the value (string)
     *   - The expected value (mixed).
     *   - The default value (mixed).
     */
    public static function providerGetValue(): array
    {
        $data = [];
        $data[] = [
          'foo', 'one',
        ];
        $data[] = [
          ['bar', 'baz'], 'two',
        ];
        $data[] = [
          ['foo', 'bar', 'baz'], null,
        ];
        $data[] = [
          'baz', 'baz', 'baz',
        ];
        $data[] = [
          null,
          [
            'foo' => 'one',
            'bar' => [
              'baz' => 'two',
            ],
          ],
        ];
        return $data;
    }

    /**
     * Tests get value modify return.
     */
    public function testGetValueModifyReturn(): void
    {
        $initial_values = $values = [
          'foo' => 'one',
          'bar' => [
            'baz' => 'two',
          ],
        ];
        $form_state = (new FormStateValuesTraitStub())->setValues($values);

        $value = &$form_state->getValue(null);
        $this->assertSame($initial_values, $value);
        $value = ['bing' => 'bang'];
        $this->assertSame(['bing' => 'bang'], $form_state->getValues());
        $this->assertSame('bang', $form_state->getValue('bing'));
        $this->assertSame(['bing' => 'bang'], $form_state->getValue(null));
    }

    /**
     * Tests set value.
     */
    #[DataProvider('providerSetValue')]
    public function testSetValue($key, $value, $expected): void
    {
        $form_state = (new FormStateValuesTraitStub())->setValues([
          'bar' => 'wrong',
        ]);
        $form_state->setValue($key, $value);
        $this->assertSame($expected, $form_state->getValues());
    }

    /**
     * Provides data to self::testSetValue().
     *
     * @return array[]
     *   Items are arrays of two items:
     *   - The key for which to set a new value (string)
     *   - The new value to set (mixed).
     *   - The expected form state values after setting the new value (mixed[]).
     */
    public static function providerSetValue(): array
    {
        $data = [];
        $data[] = [
          'foo', 'one', ['bar' => 'wrong', 'foo' => 'one'],
        ];
        $data[] = [
          ['bar', 'baz'], 'two', ['bar' => ['baz' => 'two']],
        ];
        $data[] = [
          ['foo', 'bar', 'baz'], null, ['bar' => 'wrong', 'foo' => ['bar' => ['baz' => null]]],
        ];
        return $data;
    }

    /**
     * Tests has value.
     */
    #[DataProvider('providerHasValue')]
    public function testHasValue($key, $expected): void
    {
        $form_state = (new FormStateValuesTraitStub())->setValues([
          'foo' => 'one',
          'bar' => [
            'baz' => 'two',
          ],
          'true' => true,
          'false' => false,
          'null' => null,
        ]);
        $this->assertSame($expected, $form_state->hasValue($key));
    }

    /**
     * Provides data to self::testHasValue().
     *
     * @return array[]
     *   Items are arrays of two items:
     *   - The key to check for in the form state (string)
     *   - Whether the form state has an item with that key (bool).
     */
    public static function providerHasValue(): array
    {
        $data = [];
        $data[] = [
          'foo', true,
        ];
        $data[] = [
          ['bar', 'baz'], true,
        ];
        $data[] = [
          ['foo', 'bar', 'baz'], false,
        ];
        $data[] = [
          'true', true,
        ];
        $data[] = [
          'false', true,
        ];
        $data[] = [
          'null', false,
        ];
        return $data;
    }

    /**
     * Tests is value empty.
     */
    #[DataProvider('providerIsValueEmpty')]
    public function testIsValueEmpty($key, $expected): void
    {
        $form_state = (new FormStateValuesTraitStub())->setValues([
          'foo' => 'one',
          'bar' => [
            'baz' => 'two',
          ],
          'true' => true,
          'false' => false,
          'null' => null,
        ]);
        $this->assertSame($expected, $form_state->isValueEmpty($key));
    }

    /**
     * Provides data to self::testIsValueEmpty().
     *
     * @return array[]
     *   Items are arrays of two items:
     *   - The key to check for in the form state (string)
     *   - Whether the value is empty or not (bool).
     */
    public static function providerIsValueEmpty(): array
    {
        $data = [];
        $data[] = [
          'foo', false,
        ];
        $data[] = [
          ['bar', 'baz'], false,
        ];
        $data[] = [
          ['foo', 'bar', 'baz'], true,
        ];
        $data[] = [
          'true', false,
        ];
        $data[] = [
          'false', true,
        ];
        $data[] = [
          'null', true,
        ];
        return $data;
    }

}

/**
 * Stub class with trait for testing.
 */
class FormStateValuesTraitStub
{
    use FormStateValuesTrait;

    /**
     * The submitted form values.
     *
     * @var mixed[]
     */
    protected $values = [];

    /**
     * {@inheritdoc}
     */
    public function &getValues()
    {
        return $this->values;
    }

}
