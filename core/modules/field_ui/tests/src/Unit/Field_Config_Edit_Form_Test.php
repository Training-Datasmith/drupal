<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Unit;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\field_ui\Form\FieldConfigEditForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\field_ui\Form\FieldConfigEditForm.
 */
#[CoversClass(FieldConfigEditForm::class)]
#[Group('field_ui')]
class FieldConfigEditFormTest extends UnitTestCase
{
    /**
     * The field config edit form.
     *
     * @var \Drupal\field_ui\Form\FieldConfigEditForm|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $fieldConfigEditForm;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $entity_type_bundle_info = $this->createMock('\Drupal\Core\Entity\EntityTypeBundleInfoInterface');
        $typed_data = $this->createMock('\Drupal\Core\TypedData\TypedDataManagerInterface');
        $temp_store = $this->createMock(PrivateTempStore::class);
        $element_info_manager = $this->createMock(ElementInfoManagerInterface::class);
        $entity_display_repository = $this->createMock(EntityDisplayRepositoryInterface::class);
        $this->fieldConfigEditForm = new FieldConfigEditForm($entity_type_bundle_info, $typed_data, $entity_display_repository, $temp_store, $element_info_manager);
    }

    /**
     * Tests has any required.
     */
    #[DataProvider('providerRequired')]
    public function testHasAnyRequired(array $element, bool $result): void
    {
        $reflection = new \ReflectionClass('\Drupal\field_ui\Form\FieldConfigEditForm');
        $method = $reflection->getMethod('hasAnyRequired');
        $this->assertEquals($result, $method->invoke($this->fieldConfigEditForm, $element));
    }

    /**
     * Provides test cases with required and optional elements.
     */
    public static function providerRequired(): \Generator
    {
        yield 'required' => [
          [['#required' => true]],
          true,
        ];
        yield 'optional' => [
          [['#required' => false]],
          false,
        ];
        yield 'required and optional' => [
          [['#required' => true], ['#required' => false]],
          true,
        ];
        yield 'empty' => [
          [[], []],
          false,
        ];
        yield 'multiple required' => [
          [[['#required' => true]], [['#required' => true]]],
          true,
        ];
        yield 'multiple optional' => [
          [[['#required' => false]], [['#required' => false]]],
          false,
        ];
    }

}
