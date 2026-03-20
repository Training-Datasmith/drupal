<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Functional;

use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\BrowserTestBase;

// cspell:ignore destid sourceid

/**
 * Provides base class for testing migrate messages.
 */
abstract class MigrateMessageTestBase extends BrowserTestBase
{
    /**
     * {@inheritdoc}
     */
    protected static $modules = [
      'message_test',
      'migrate',
    ];

    /**
     * {@inheritdoc}
     */
    protected $defaultTheme = 'stark';

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * Migration IDs.
     *
     * @var string[]
     */
    protected $migrationIds = ['custom_test'];

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->createUser(['view migration messages']);
        $this->drupalLogin($user);
        $this->database = \Drupal::database();
    }

    /**
     * Creates map and message tables for testing.
     *
     * @see \Drupal\migrate\Plugin\migrate\id_map\Sql::ensureTables
     */
    protected function createTables($migration_ids): void
    {
        foreach ($migration_ids as $migration_id) {
            $map_table_name = "migrate_map_$migration_id";
            $message_table_name = "migrate_message_$migration_id";

            if (!$this->database->schema()->tableExists($map_table_name)) {
                $fields = [];
                $fields['source_ids_hash'] = [
                  'type' => 'varchar',
                  'length' => '64',
                  'not null' => true,
                ];
                $fields['sourceid1'] = [
                  'type' => 'varchar',
                  'length' => '255',
                  'not null' => true,
                ];
                $fields['destid1'] = [
                  'type' => 'varchar',
                  'length' => '255',
                  'not null' => false,
                ];
                $fields['source_row_status'] = [
                  'type' => 'int',
                  'size' => 'tiny',
                  'unsigned' => true,
                  'not null' => true,
                  'default' => MigrateIdMapInterface::STATUS_IMPORTED,
                ];
                $fields['rollback_action'] = [
                  'type' => 'int',
                  'size' => 'tiny',
                  'unsigned' => true,
                  'not null' => true,
                  'default' => MigrateIdMapInterface::ROLLBACK_DELETE,
                ];
                $fields['last_imported'] = [
                  'type' => 'int',
                  'unsigned' => true,
                  'not null' => true,
                  'default' => 0,
                ];
                $fields['hash'] = [
                  'type' => 'varchar',
                  'length' => '64',
                  'not null' => false,
                ];
                $schema = [
                  'description' => '',
                  'fields' => $fields,
                  'primary key' => ['source_ids_hash'],
                ];
                $this->database->schema()->createTable($map_table_name, $schema);

                $rows = [
                  [
                    'source_ids_hash' => '37c655d',
                    'sourceid1' => 'navigation',
                    'destid1' => 'tools',
                    'source_row_status' => '0',
                    'rollback_action' => '1',
                    'last_imported' => '0',
                    'hash' => '',
                  ],
                  [
                    'source_ids_hash' => '3a34190',
                    'sourceid1' => 'menu-fixed-lang',
                    'destid1' => 'menu-fixed-lang',
                    'source_row_status' => '0',
                    'rollback_action' => '0',
                    'last_imported' => '0',
                    'hash' => '',
                  ],
                  [
                    'source_ids_hash' => '3e51f67',
                    'sourceid1' => 'management',
                    'destid1' => 'admin',
                    'source_row_status' => '0',
                    'rollback_action' => '1',
                    'last_imported' => '0',
                    'hash' => '',
                  ],
                  [
                    'source_ids_hash' => '94a5caa',
                    'sourceid1' => 'user-menu',
                    'destid1' => 'account',
                    'source_row_status' => '0',
                    'rollback_action' => '1',
                    'last_imported' => '0',
                    'hash' => '',
                  ],
                  [
                    'source_ids_hash' => 'c0efbcca',
                    'sourceid1' => 'main-menu',
                    'destid1' => 'main',
                    'source_row_status' => '0',
                    'rollback_action' => '1',
                    'last_imported' => '0',
                    'hash' => '',
                  ],
                  [
                    'source_ids_hash' => 'f64cb72f',
                    'sourceid1' => 'menu-test-menu',
                    'destid1' => 'menu-test-menu',
                    'source_row_status' => '0',
                    'rollback_action' => '0',
                    'last_imported' => '0',
                    'hash' => '',
                  ],
                ];
                foreach ($rows as $row) {
                    $this->database->insert($map_table_name)->fields($row)->execute();
                }
            }

            if (!$this->database->schema()->tableExists($message_table_name)) {
                $fields = [];
                $fields['msgid'] = [
                  'type' => 'serial',
                  'unsigned' => true,
                  'not null' => true,
                ];
                $fields['source_ids_hash'] = [
                  'type' => 'varchar',
                  'length' => '64',
                  'not null' => true,
                ];
                $fields['level'] = [
                  'type' => 'int',
                  'unsigned' => true,
                  'not null' => true,
                  'default' => 1,
                ];
                $fields['message'] = [
                  'type' => 'text',
                  'size' => 'medium',
                  'not null' => true,
                ];
                $schema = [
                  'description' => '',
                  'fields' => $fields,
                  'primary key' => ['msgid'],
                ];
                $this->database->schema()->createTable($message_table_name, $schema);

                $rows = [
                  [
                    'msgid' => '1',
                    'source_ids_hash' => '28cfb3d1',
                    'level' => (string) MigrationInterface::MESSAGE_ERROR,
                    'message' => 'Config entities can not be stubbed.',
                  ],
                  [
                    'msgid' => '2',
                    'source_ids_hash' => '28cfb3d1',
                    'level' => (string) MigrationInterface::MESSAGE_ERROR,
                    'message' => 'Config entities can not be stubbed.',
                  ],
                  [
                    'msgid' => '3',
                    'source_ids_hash' => '05914d93',
                    'level' => (string) MigrationInterface::MESSAGE_ERROR,
                    'message' => 'Config entities can not be stubbed.',
                  ],
                  [
                    'msgid' => '4',
                    'source_ids_hash' => '05914d93',
                    'level' => (string) MigrationInterface::MESSAGE_INFORMATIONAL,
                    'message' => 'Config entities can not be stubbed.',
                  ],
                ];
                foreach ($rows as $row) {
                    $this->database->insert($message_table_name)->fields($row)->execute();
                }
            }
        }
    }

}
