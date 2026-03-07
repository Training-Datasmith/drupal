<?php

declare(strict_types=1);

namespace Drupal\Tests\package_manager\Unit;

use Drupal\package_manager\SandboxManagerBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\package_manager\SandboxManagerBase.
 *
 * @internal
 */
#[CoversClass(SandboxManagerBase::class)]
#[Group('package_manager')]
class SandboxManagerBaseTest extends UnitTestCase
{
    /**
     * Tests validate requirements.
     *
     * @param string|null $expected_exception
     *   The exception class that should be thrown, or NULL if there should not be
     *   any exception.
     * @param string $requirement
     *   The requirement (package name and optional constraint) to validate.
     */
    #[DataProvider('providerValidateRequirements')]
    public function testValidateRequirements(?string $expected_exception, string $requirement): void
    {
        $reflector = new \ReflectionClass(SandboxManagerBase::class);
        $method = $reflector->getMethod('validateRequirements');

        if ($expected_exception) {
            $this->expectException($expected_exception);
        } else {
            $this->assertNull($expected_exception);
        }

        $method->invoke(null, [$requirement]);
    }

    /**
     * Data provider for testValidateRequirements.
     *
     * @return array[]
     *   The test cases.
     */
    public static function providerValidateRequirements(): array
    {
        return [
          // Valid requirements.
          [null, 'vendor/package'],
          [null, 'vendor/snake_case'],
          [null, 'vendor/kebab-case'],
          [null, 'vendor/with.dots'],
          [null, '1vendor2/3package4'],
          [null, 'vendor/package:1'],
          [null, 'vendor/package:1.2'],
          [null, 'vendor/package:1.2.3'],
          [null, 'vendor/package:1.x'],
          [null, 'vendor/package:^1'],
          [null, 'vendor/package:~1'],
          [null, 'vendor/package:>1'],
          [null, 'vendor/package:<1'],
          [null, 'vendor/package:>=1'],
          [null, 'vendor/package:>1 <2'],
          [null, 'vendor/package:1 || 2'],
          [null, 'vendor/package:>=1,<1.1.0'],
          [null, 'vendor/package:1a'],
          [null, 'vendor/package:*'],
          [null, 'vendor/package:dev-master'],
          [null, 'vendor/package:*@dev'],
          [null, 'vendor/package:@dev'],
          [null, 'vendor/package:master@dev'],
          [null, 'vendor/package:master@beta'],
          [null, 'php'],
          [null, 'php:8'],
          [null, 'php:8.0'],
          [null, 'php:^8.1'],
          [null, 'php:~8.1'],
          [null, 'php-64bit'],
          [null, 'composer'],
          [null, 'composer-plugin-api'],
          [null, 'composer-plugin-api:1'],
          [null, 'ext-json'],
          [null, 'ext-json:1'],
          [null, 'ext-pdo_mysql'],
          [null, 'ext-pdo_mysql:1'],
          [null, 'lib-curl'],
          [null, 'lib-curl:1'],
          [null, 'lib-curl-zlib'],
          [null, 'lib-curl-zlib:1'],

          // Invalid requirements.
          [\InvalidArgumentException::class, ''],
          [\InvalidArgumentException::class, ' '],
          [\InvalidArgumentException::class, '/'],
          [\InvalidArgumentException::class, 'php8'],
          [\InvalidArgumentException::class, 'package'],
          [\InvalidArgumentException::class, 'vendor\package'],
          [\InvalidArgumentException::class, 'vendor//package'],
          [\InvalidArgumentException::class, 'vendor/package1 vendor/package2'],
          [\InvalidArgumentException::class, 'vendor/package/extra'],
          [\UnexpectedValueException::class, 'vendor/package:a'],
          [\UnexpectedValueException::class, 'vendor/package:'],
          [\UnexpectedValueException::class, 'vendor/package::'],
          [\UnexpectedValueException::class, 'vendor/package::1'],
          [\UnexpectedValueException::class, 'vendor/package:1:2'],
          [\UnexpectedValueException::class, 'vendor/package:develop@dev@dev'],
          [\UnexpectedValueException::class, 'vendor/package:develop@'],
          [\InvalidArgumentException::class, 'vEnDor/pAcKaGe'],
          [\InvalidArgumentException::class, '_vendor/package'],
          [\InvalidArgumentException::class, '_vendor/_package'],
          [\InvalidArgumentException::class, 'vendor_/package'],
          [\InvalidArgumentException::class, '_vendor/package_'],
          [\InvalidArgumentException::class, 'vendor/package-'],
          [\InvalidArgumentException::class, 'php-'],
          [\InvalidArgumentException::class, 'ext'],
          [\InvalidArgumentException::class, 'lib'],
        ];
    }

    /**
     * Tests type must be explicitly overridden.
     *
     * @legacy-covers ::getType
     */
    public function testTypeMustBeExplicitlyOverridden(): void
    {
        $good_grandchild = new class () extends ChildSandboxManager {
            /**
             * {@inheritdoc}
             */
            // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
            protected string $type = 'package_manager:good_grandchild';

        };
        $this->assertSame('package_manager:good_grandchild', $good_grandchild->getType());

        $bad_grandchild = new class () extends ChildSandboxManager {};
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(get_class($bad_grandchild) . ' must explicitly override the $type property.');
        $bad_grandchild->getType();
    }

}

/**
 * Test class for testing the child stage.
 */
class ChildSandboxManager extends SandboxManagerBase
{
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected string $type = 'package_manager:child';

}
