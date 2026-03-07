<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Condition;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests Drupal\Core\Condition\ConditionAccessResolverTrait.
 */
#[CoversClass(ConditionAccessResolverTrait::class)]
#[Group('Condition')]
class ConditionAccessResolverTraitTest extends UnitTestCase
{
    /**
     * Tests the resolveConditions() method.
     */
    #[DataProvider('providerTestResolveConditions')]
    public function testResolveConditions($conditions, $logic, $expected): void
    {
        $mocks['true'] = $this->createMock('Drupal\Core\Condition\ConditionInterface');
        $mocks['true']->expects($this->any())
          ->method('execute')
          ->willReturn(true);
        $mocks['false'] = $this->createMock('Drupal\Core\Condition\ConditionInterface');
        $mocks['false']->expects($this->any())
          ->method('execute')
          ->willReturn(false);
        $mocks['exception'] = $this->createMock('Drupal\Core\Condition\ConditionInterface');
        $mocks['exception']->expects($this->any())
          ->method('execute')
          ->will($this->throwException(new ContextException()));
        $mocks['exception']->expects($this->any())
          ->method('isNegated')
          ->willReturn(false);
        $mocks['negated'] = $this->createMock('Drupal\Core\Condition\ConditionInterface');
        $mocks['negated']->expects($this->any())
          ->method('execute')
          ->will($this->throwException(new ContextException()));
        $mocks['negated']->expects($this->any())
          ->method('isNegated')
          ->willReturn(true);

        $conditions = array_map(fn ($id): ConditionInterface&MockObject => $mocks[$id], $conditions);

        $trait_object = new TestConditionAccessResolverTrait();
        $this->assertEquals($expected, $trait_object->resolveConditions($conditions, $logic));
    }

    public static function providerTestResolveConditions()
    {
        yield [[], 'and', true];
        yield [[], 'or', false];
        yield [['false'], 'or', false];
        yield [['false'], 'and', false];
        yield [['true'], 'or', true];
        yield [['true'], 'and', true];
        yield [['true', 'false'], 'or', true];
        yield [['true', 'false'], 'and', false];
        yield [['exception'], 'or', false];
        yield [['exception'], 'and', false];
        yield [['true', 'exception'], 'or', true];
        yield [['true', 'exception'], 'and', false];
        yield [['exception', 'true'], 'or', true];
        yield [['exception', 'true'], 'and', false];
        yield [['false', 'exception'], 'or', false];
        yield [['false', 'exception'], 'and', false];
        yield [['exception', 'false'], 'or', false];
        yield [['exception', 'false'], 'and', false];
        yield [['negated'], 'or', true];
        yield [['negated'], 'and', true];
        yield [['negated', 'negated'], 'or', true];
        yield [['negated', 'negated'], 'and', true];
    }

}

/**
 * Stub class for testing trait.
 */
class TestConditionAccessResolverTrait
{
    use ConditionAccessResolverTrait {
        resolveConditions as public;
    }

}
