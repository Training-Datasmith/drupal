<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\Validation\Plugin\Validation\Constraint\SerializedConstraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\SerializedConstraintValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests serialize validator.
 */
#[CoversClass(SerializedConstraintValidator::class)]
#[Group('Validation')]
class SerializedConstraintValidatorTest extends UnitTestCase
{
    /**
     * Validate serializer constraint.
     */
    #[DataProvider('provideTestValidate')]
    public function testValidate($value, bool $valid): void
    {
        $typed_data = new StringData(DataDefinition::create('string'));
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->any())
          ->method('getObject')
          ->willReturn($typed_data);

        if ($valid) {
            $context->expects($this->never())
              ->method('addViolation');
        } else {
            $context->expects($this->once())
              ->method('addViolation');
        }

        $constraint = new SerializedConstraint();

        $validate = new SerializedConstraintValidator();
        $validate->initialize($context);
        $validate->validate($value, $constraint);
    }

    /**
     * Provides an array with several serialized and non-serialized values.
     *
     * @return array
     *   An array with test scenarios.
     */
    public static function provideTestValidate(): array
    {
        $data = [];

        $data[] = [serialize(''), true];
        $data[] = [serialize('0'), true];
        $data[] = [serialize('false'), true];
        $data[] = [serialize(0), true];
        $data[] = [serialize(1), true];
        $data[] = [serialize(true), true];
        $data[] = [serialize(false), true];
        $data[] = ['non serialized string', false];
        $data[] = [true, false];
        $data[] = [new \stdClass(), false];

        return $data;
    }

}
