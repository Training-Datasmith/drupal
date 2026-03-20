<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Installer;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\DatabaseNotFoundException;
use Drupal\Core\Installer\InstallerRedirectTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\Core\Database\Stub\StubSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests Drupal\Core\Installer\InstallerRedirectTrait.
 */
#[CoversClass(InstallerRedirectTrait::class)]
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerRedirectTraitTest extends KernelTestBase
{
    /**
     * Data provider for testShouldRedirectToInstaller().
     *
     * @return array
     *   - Expected result from shouldRedirectToInstaller().
     *   - Exceptions to be handled by shouldRedirectToInstaller()
     *   - Whether or not there is a database connection.
     *   - Whether or not there is database connection info.
     *   - Whether or not the key_value table exists in the database.
     */
    public static function providerShouldRedirectToInstaller(): array
    {
        return [
          [true, DatabaseNotFoundException::class, false, false],
          [true, DatabaseNotFoundException::class, true, false],
          [true, DatabaseNotFoundException::class, false, true],
          [true, DatabaseNotFoundException::class, true, true],
          [true, DatabaseNotFoundException::class, true, true, false],

          [true, \PDOException::class, false, false],
          [true, \PDOException::class, true, false],
          [false, \PDOException::class, false, true],
          [false, \PDOException::class, true, true],
          [true, \PDOException::class, true, true, false],

          [true, DatabaseExceptionWrapper::class, false, false],
          [true, DatabaseExceptionWrapper::class, true, false],
          [false, DatabaseExceptionWrapper::class, false, true],
          [false, DatabaseExceptionWrapper::class, true, true],
          [true, DatabaseExceptionWrapper::class, true, true, false],

          [true, NotFoundHttpException::class, false, false],
          [true, NotFoundHttpException::class, true, false],
          [false, NotFoundHttpException::class, false, true],
          [false, NotFoundHttpException::class, true, true],
          [true, NotFoundHttpException::class, true, true, false],

          [false, \Exception::class, false, false],
          [false, \Exception::class, true, false],
          [false, \Exception::class, false, true],
          [false, \Exception::class, true, true],
          [false, \Exception::class, true, true, false],
        ];
    }

    /**
     * Tests should redirect to installer.
     */
    #[DataProvider('providerShouldRedirectToInstaller')]
    public function testShouldRedirectToInstaller(bool $expected, string $exception, bool $connection, bool $connection_info, bool $key_value_table_exists = true): void
    {
        // Mock the trait.
        $trait = $this->getMockBuilder(InstallerRedirectTraitMockableClass::class)
          ->onlyMethods(['isCli'])
          ->getMock();

        // Make sure that the method thinks we are not using the cli.
        $trait->expects($this->any())
          ->method('isCli')
          ->willReturn(false);

        // If testing no connection info, we need to make the 'default' key not
        // visible.
        if (!$connection_info) {
            Database::renameConnection('default', __METHOD__);
        }

        if ($connection) {
            // Mock the database connection.
            $connection = $this->getMockBuilder(StubConnection::class)
              ->disableOriginalConstructor()
              ->onlyMethods(['schema'])
              ->getMock();

            if ($connection_info) {
                // Mock the database schema class.
                $schema = $this->getMockBuilder(StubSchema::class)
                  ->disableOriginalConstructor()
                  ->onlyMethods(['tableExists'])
                  ->getMock();

                $schema->expects($this->any())
                  ->method('tableExists')
                  ->with('key_value')
                  ->willReturn($key_value_table_exists);

                $connection->expects($this->any())
                  ->method('schema')
                  ->willReturn($schema);
            }
        } else {
            // Set the database connection if there is none.
            $connection = null;
        }

        try {
            throw new $exception();
        } catch (\Exception $e) {
            // Call shouldRedirectToInstaller.
            $method_ref = new \ReflectionMethod($trait, 'shouldRedirectToInstaller');
            $this->assertSame($expected, $method_ref->invoke($trait, $e, $connection));
        }

        if (!$connection_info) {
            Database::renameConnection(__METHOD__, 'default');
        }
    }

}
