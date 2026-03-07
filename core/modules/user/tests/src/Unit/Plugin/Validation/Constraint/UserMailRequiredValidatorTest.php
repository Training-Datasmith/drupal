<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Unit\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Plugin\Validation\Constraint\UserMailRequired;
use Drupal\user\Plugin\Validation\Constraint\UserMailRequiredValidator;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Prophet;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests Drupal\user\Plugin\Validation\Constraint\UserMailRequiredValidator.
 */
#[CoversClass(UserMailRequiredValidator::class)]
#[Group('user')]
class UserMailRequiredValidatorTest extends UnitTestCase
{
    /**
     * Creates a validator instance.
     *
     * @param bool $is_admin
     *   Whether or not the current user is an administrator.
     *
     * @return \Drupal\user\Plugin\Validation\Constraint\UserMailRequiredValidator
     *   The validator instance.
     */
    protected function createValidator($is_admin)
    {
        // Setup mocks that don't need to change.
        $unchanged_account = $this->prophesize(UserInterface::class);
        $unchanged_account->getEmail()->willReturn(null);

        $user_storage = $this->prophesize(UserStorageInterface::class);
        $user_storage->loadUnchanged(3)->willReturn($unchanged_account->reveal());

        $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
        $entity_type_manager->getStorage('user')->willReturn($user_storage->reveal());

        $current_user = $this->prophesize(AccountInterface::class);
        $current_user->id()->willReturn(3);
        $current_user->hasPermission('administer users')->willReturn($is_admin);
        $container = new ContainerBuilder();
        $container->set('entity_type.manager', $entity_type_manager->reveal());
        $container->set('current_user', $current_user->reveal());
        \Drupal::setContainer($container);
        return new UserMailRequiredValidator();
    }

    /**
     * Tests validate.
     */
    #[DataProvider('providerTestValidate')]
    public function testValidate($items, $expected_violation, $is_admin = false): void
    {
        $constraint = new UserMailRequired();

        // If a violation is expected, then the context's addViolation method will
        // be called, otherwise it should not be called.
        $context = $this->prophesize(ExecutionContextInterface::class);

        if ($expected_violation) {
            $context->addViolation('@name field is required.', ['@name' => 'Email'])->shouldBeCalledTimes(1);
        } else {
            $context->addViolation()->shouldNotBeCalled();
        }

        $validator = $this->createValidator($is_admin);
        $validator->initialize($context->reveal());
        $validator->validate($items, $constraint);
    }

    /**
     * Data provider for ::testValidate().
     */
    public static function providerTestValidate()
    {
        $prophet = new Prophet();
        $cases = [];

        // Case 1: Empty user should be ignored.
        $items = $prophet->prophesize(FieldItemListInterface::class);
        $items->getEntity()->willReturn(null)->shouldBeCalledTimes(1);
        $cases['Empty user should be ignored'] = [$items->reveal(), false];

        // Case 2: New users without an email should add a violation.
        $items = $prophet->prophesize(FieldItemListInterface::class);
        $account = $prophet->prophesize(UserInterface::class);
        $account->isNew()->willReturn(true);
        $account->id()->shouldNotBeCalled();
        $field_definition = $prophet->prophesize(FieldDefinitionInterface::class);
        $field_definition->getLabel()->willReturn('Email');
        $account->getFieldDefinition('mail')->willReturn($field_definition->reveal())->shouldBeCalledTimes(1);
        $items->getEntity()->willReturn($account->reveal())->shouldBeCalledTimes(1);
        $items->isEmpty()->willReturn(true);
        $cases['New users without an email should add a violation'] = [$items->reveal(), true];

        // Case 3: Existing users without an email should add a violation.
        $items = $prophet->prophesize(FieldItemListInterface::class);
        $account = $prophet->prophesize(UserInterface::class);
        $account->isNew()->willReturn(false);
        $account->id()->willReturn(3);
        $field_definition = $prophet->prophesize(FieldDefinitionInterface::class);
        $field_definition->getLabel()->willReturn('Email');
        $account->getFieldDefinition('mail')->willReturn($field_definition->reveal())->shouldBeCalledTimes(1);
        $items->getEntity()->willReturn($account->reveal())->shouldBeCalledTimes(1);
        $items->isEmpty()->willReturn(true);
        $cases['Existing users without an email should add a violation'] = [$items->reveal(), true];

        // Case 4: New user with an email is valid.
        $items = $prophet->prophesize(FieldItemListInterface::class);
        $account = $prophet->prophesize(UserInterface::class);
        $account->isNew()->willReturn(true);
        $account->id()->shouldNotBeCalled();
        $field_definition = $prophet->prophesize(FieldDefinitionInterface::class);
        $field_definition->getLabel()->willReturn('Email');
        $account->getFieldDefinition('mail')->willReturn($field_definition->reveal())->shouldBeCalledTimes(1);
        $items->getEntity()->willReturn($account->reveal())->shouldBeCalledTimes(1);
        $items->isEmpty()->willReturn(false);
        $cases['New user with an email is valid'] = [$items->reveal(), false];

        // Case 5: Existing users with an email should be ignored.
        $items = $prophet->prophesize(FieldItemListInterface::class);
        $account = $prophet->prophesize(UserInterface::class);
        $account->isNew()->willReturn(false);
        $account->id()->willReturn(3);
        $field_definition = $prophet->prophesize(FieldDefinitionInterface::class);
        $field_definition->getLabel()->willReturn('Email');
        $account->getFieldDefinition('mail')->willReturn($field_definition->reveal())->shouldBeCalledTimes(1);
        $items->getEntity()->willReturn($account->reveal())->shouldBeCalledTimes(1);
        $items->isEmpty()->willReturn(false);
        $cases['Existing users with an email should be ignored'] = [$items->reveal(), false];

        // Case 6: Existing users without an email should be ignored if the current
        // user is an administrator.
        $items = $prophet->prophesize(FieldItemListInterface::class);
        $account = $prophet->prophesize(UserInterface::class);
        $account->isNew()->willReturn(false);
        $account->id()->willReturn(3);
        $field_definition = $prophet->prophesize(FieldDefinitionInterface::class);
        $field_definition->getLabel()->willReturn('Email');
        $account->getFieldDefinition('mail')->willReturn($field_definition->reveal())->shouldBeCalledTimes(1);
        $items->getEntity()->willReturn($account->reveal())->shouldBeCalledTimes(1);
        $items->isEmpty()->willReturn(true);
        $cases['Existing users without an email should be ignored if the current user is an administrator.'] = [
          $items->reveal(),
          false,
          true,
        ];

        return $cases;
    }

}
