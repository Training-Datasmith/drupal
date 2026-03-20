<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Kernel;

use Drupal\block_content\BlockContentAccessControlHandler;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the block content entity access handler.
 */
#[CoversClass(BlockContentAccessControlHandler::class)]
#[Group('block_content')]
#[RunTestsInSeparateProcesses]
class BlockContentAccessHandlerTest extends KernelTestBase
{
    use UserCreationTrait;

    /**
     * {@inheritdoc}
     */
    protected static $modules = [
      'block_content',
      'system',
      'user',
    ];

    /**
     * The BlockContent access controller to test.
     *
     * @var \Drupal\block_content\BlockContentAccessControlHandler
     */
    protected $accessControlHandler;

    /**
     * The BlockContent entity used for testing.
     *
     * @var \Drupal\block_content\Entity\BlockContent
     */
    protected $blockEntity;

    /**
     * The test role.
     *
     * @var \Drupal\user\RoleInterface
     */
    protected $role;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->installSchema('user', ['users_data']);
        $this->installEntitySchema('user');
        $this->installEntitySchema('block_content');

        // Create a basic block content type.
        $block_content_type = BlockContentType::create([
          'id' => 'basic',
          'label' => 'A basic block type',
          'description' => 'Provides a block type that is basic.',
        ]);
        $block_content_type->save();

        // Create a square block content type.
        $block_content_type = BlockContentType::create([
          'id' => 'square',
          'label' => 'A square block type',
          'description' => 'Provides a block type that is square.',
        ]);
        $block_content_type->save();

        $this->blockEntity = BlockContent::create([
          'info' => 'The Block',
          'type' => 'square',
        ]);
        $this->blockEntity->save();

        // Create user 1 test does not have all permissions.
        User::create([
          'name' => 'admin',
        ])->save();

        $this->role = Role::create([
          'id' => 'test',
          'label' => 'test role',
        ]);
        $this->role->save();
        $this->accessControlHandler = new BlockContentAccessControlHandler(\Drupal::entityTypeManager()->getDefinition('block_content'), \Drupal::service('event_dispatcher'));
    }

    /**
     * Test block content entity access.
     *
     * @param string $operation
     *   The entity operation to test.
     * @param bool $published
     *   Whether the latest revision should be published.
     * @param bool $reusable
     *   Whether the block content should be reusable. Non-reusable blocks are
     *   typically used in Layout Builder.
     * @param array $permissions
     *   Permissions to grant to the test user.
     * @param bool $isLatest
     *   Whether the block content should be the latest revision when checking
     *   access. If FALSE, multiple revisions will be created, and an older
     *   revision will be loaded before checking access.
     * @param string|null $parent_access
     *   Whether the test user has access to the parent entity, valid values are
     *   class names of classes implementing AccessResultInterface. Set to NULL to
     *   assert parent will not be called.
     * @param string $expected_access
     *   The expected access for the user and block content. Valid values are
     *   class names of classes implementing AccessResultInterface.
     * @param string|null $expected_access_message
     *   The expected access message.
     *
     * @phpstan-param class-string<\Drupal\Core\Access\AccessResultInterface>|null $parent_access
     * @phpstan-param class-string<\Drupal\Core\Access\AccessResultInterface> $expected_access
     * @legacy-covers ::checkAccess
     */
    #[DataProvider('providerTestAccess')]
    public function testAccess(string $operation, bool $published, bool $reusable, array $permissions, bool $isLatest, ?string $parent_access, string $expected_access, ?string $expected_access_message = null): void
    {
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
        $entityStorage = \Drupal::entityTypeManager()->getStorage('block_content');

        $loadRevisionId = null;
        if (!$isLatest) {
            // Save a historical revision, then setup for a new revision to be saved.
            $this->blockEntity->save();
            $loadRevisionId = $this->blockEntity->getRevisionId();
            $this->blockEntity = $entityStorage->createRevision($this->blockEntity);
        }

        $published ? $this->blockEntity->setPublished() : $this->blockEntity->setUnpublished();
        $reusable ? $this->blockEntity->setReusable() : $this->blockEntity->setNonReusable();

        $user = User::create([
          'name' => 'Someone',
          'mail' => 'hi@example.com',
        ]);

        if ($permissions) {
            foreach ($permissions as $permission) {
                $this->role->grantPermission($permission);
            }
            $this->role->save();
        }
        $user->addRole($this->role->id())->save();

        if ($parent_access !== null) {
            $parent_entity = $this->prophesize(AccessibleInterface::class);
            $expected_parent_result = new ($parent_access)();
            $parent_entity->access($operation, $user, true)
              ->willReturn($expected_parent_result)
              ->shouldBeCalled();

            $this->blockEntity->setAccessDependency($parent_entity->reveal());

        }
        $this->blockEntity->save();

        // Reload a previous revision.
        if ($loadRevisionId !== null) {
            $this->blockEntity = $entityStorage->loadRevision($loadRevisionId);
        }

        $result = $this->accessControlHandler->access($this->blockEntity, $operation, $user, true);
        $this->assertInstanceOf($expected_access, $result);
        if ($expected_access_message !== null) {
            $this->assertInstanceOf(AccessResultReasonInterface::class, $result);
            $this->assertEquals($expected_access_message, $result->getReason());
        }
    }

    /**
     * Data provider for testAccess().
     */
    public static function providerTestAccess(): array
    {
        $cases = [
          'view:published:reusable' => [
            'view',
            true,
            true,
            [],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'view:unpublished:reusable' => [
            'view',
            false,
            true,
            [],
            true,
            null,
            AccessResultNeutral::class,
          ],
          'view:unpublished:reusable:admin' => [
            'view',
            false,
            true,
            ['access block library'],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'view:unpublished:reusable:per-block-editor:basic' => [
            'view',
            false,
            true,
            ['edit any basic block content'],
            true,
            null,
            AccessResultNeutral::class,
          ],
          'view:unpublished:reusable:per-block-editor:square' => [
            'view',
            false,
            true,
            ['access block library', 'edit any basic block content'],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'view:published:reusable:admin' => [
            'view',
            true,
            true,
            ['access block library'],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'view:published:reusable:per-block-editor:basic' => [
            'view',
            true,
            true,
            ['access block library', 'edit any basic block content'],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'view:published:reusable:per-block-editor:square' => [
            'view',
            true,
            true,
            ['access block library', 'edit any square block content'],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'view:published:non_reusable' => [
            'view',
            true,
            false,
            [],
            true,
            null,
            AccessResultForbidden::class,
          ],
          'view:published:non_reusable:parent_allowed' => [
            'view',
            true,
            false,
            [],
            true,
            AccessResultAllowed::class,
            AccessResultAllowed::class,
          ],
          'view:published:non_reusable:parent_neutral' => [
            'view',
            true,
            false,
            [],
            true,
            AccessResultNeutral::class,
            AccessResultNeutral::class,
          ],
          'view:published:non_reusable:parent_forbidden' => [
            'view',
            true,
            false,
            [],
            true,
            AccessResultForbidden::class,
            AccessResultForbidden::class,
          ],
        ];
        foreach (['update', 'delete'] as $operation) {
            $label = $operation === 'update' ? 'edit' : 'delete';
            $cases += [
              $operation . ':published:reusable' => [
                $operation,
                true,
                true,
                [],
                true,
                null,
                AccessResultNeutral::class,
              ],
              $operation . ':unpublished:reusable' => [
                $operation,
                false,
                true,
                [],
                true,
                null,
                AccessResultNeutral::class,
              ],
              $operation . ':unpublished:reusable:admin' => [
                $operation,
                false,
                true,
                [$label . ' any square block content'],
                true,
                null,
                AccessResultAllowed::class,
              ],
              $operation . ':published:reusable:admin' => [
                $operation,
                true,
                true,
                [$label . ' any square block content'],
                true,
                null,
                AccessResultAllowed::class,
              ],
              $operation . ':published:non_reusable' => [
                $operation,
                true,
                false,
                [],
                true,
                null,
                AccessResultForbidden::class,
              ],
              $operation . ':published:non_reusable:parent_allowed' => [
                $operation,
                true,
                false,
                [],
                true,
                AccessResultAllowed::class,
                AccessResultNeutral::class,
              ],
              $operation . ':published:non_reusable:parent_neutral' => [
                $operation,
                true,
                false,
                [],
                true,
                AccessResultNeutral::class,
                AccessResultNeutral::class,
              ],
              $operation . ':published:non_reusable:parent_forbidden' => [
                $operation,
                true,
                false,
                [],
                true,
                AccessResultForbidden::class,
                AccessResultForbidden::class,
              ],
              $operation . ':unpublished:reusable:per-block-editor:basic' => [
                $operation,
                false,
                true,
                ['edit any basic block content'],
                true,
                null,
                AccessResultNeutral::class,
              ],
              $operation . ':published:reusable:per-block-editor:basic' => [
                $operation,
                true,
                true,
                ['edit any basic block content'],
                true,
                null,
                AccessResultNeutral::class,
              ],
            ];
        }

        $cases += [
          'update:unpublished:reusable:per-block-editor:square' => [
            'update',
            false,
            true,
            ['edit any square block content'],
            true,
            null,
            AccessResultAllowed::class,
          ],
          'update:published:reusable:per-block-editor:square' => [
            'update',
            true,
            true,
            ['edit any square block content'],
            true,
            null,
            AccessResultAllowed::class,
          ],
        ];

        $cases += [
          'delete:unpublished:reusable:per-block-editor:square' => [
            'delete',
            false,
            true,
            ['edit any square block content'],
            true,
            null,
            AccessResultNeutral::class,
          ],
          'delete:published:reusable:per-block-editor:square' => [
            'delete',
            true,
            true,
            ['edit any square block content'],
            true,
            null,
            AccessResultNeutral::class,
          ],
        ];

        // View all revisions:
        $cases['view all revisions:none'] = [
          'view all revisions',
          true,
          true,
          [],
          true,
          null,
          AccessResultNeutral::class,
        ];
        $cases['view all revisions:view any bundle history'] = [
          'view all revisions',
          true,
          true,
          ['view any square block content history'],
          true,
          null,
          AccessResultAllowed::class,
        ];
        $cases['view all revisions:administer block content'] = [
          'view all revisions',
          true,
          true,
          ['administer block content'],
          true,
          null,
          AccessResultAllowed::class,
        ];

        // Revert revisions:
        $cases['revert:none:latest'] = [
          'revert',
          true,
          true,
          [],
          true,
          null,
          AccessResultForbidden::class,
        ];
        $cases['revert:none:historical'] = [
          'revert',
          true,
          true,
          [],
          false,
          null,
          AccessResultNeutral::class,
        ];
        $cases['revert:revert bundle:historical'] = [
          'revert',
          true,
          true,
          ['revert any square block content revisions'],
          false,
          null,
          AccessResultAllowed::class,
        ];
        $cases['revert:administer block content:latest'] = [
          'revert',
          true,
          true,
          ['administer block content'],
          true,
          null,
          AccessResultForbidden::class,
        ];
        $cases['revert:administer block content:historical'] = [
          'revert',
          true,
          true,
          ['administer block content'],
          false,
          null,
          AccessResultAllowed::class,
        ];
        $cases['revert:revert bundle:historical:non reusable'] = [
          'revert',
          true,
          false,
          ['revert any square block content revisions'],
          false,
          null,
          AccessResultForbidden::class,
          'Block content must be reusable to use `revert` operation',
        ];

        // Delete revisions:
        $cases['delete revision:none:latest'] = [
          'delete revision',
          true,
          true,
          [],
          true,
          null,
          AccessResultForbidden::class,
        ];
        $cases['delete revision:none:historical'] = [
          'delete revision',
          true,
          true,
          [],
          false,
          null,
          AccessResultNeutral::class,
        ];
        $cases['delete revision:administer block content:latest'] = [
          'delete revision',
          true,
          true,
          ['administer block content'],
          true,
          null,
          AccessResultForbidden::class,
        ];
        $cases['delete revision:administer block content:historical'] = [
          'delete revision',
          true,
          true,
          ['administer block content'],
          false,
          null,
          AccessResultAllowed::class,
        ];
        $cases['delete revision:delete bundle:latest'] = [
          'delete revision',
          true,
          true,
          ['administer block content'],
          true,
          null,
          AccessResultForbidden::class,
        ];
        $cases['delete revision:delete bundle:historical'] = [
          'delete revision',
          true,
          true,
          ['delete any square block content revisions'],
          false,
          null,
          AccessResultAllowed::class,
        ];
        $cases['delete revision:delete bundle:historical:non reusable'] = [
          'delete revision',
          true,
          false,
          ['delete any square block content revisions'],
          false,
          null,
          AccessResultForbidden::class,
          'Block content must be reusable to use `delete revision` operation',
        ];

        return $cases;
    }

    /**
     * Tests revision log access.
     */
    public function testRevisionLogAccess(): void
    {
        $admin = $this->createUser([
          'administer block content',
          'access content',
        ]);
        $editor = $this->createUser([
          'access content',
          'access block library',
          'view any square block content history',
        ]);
        $viewer = $this->createUser([
          'access content',
        ]);

        $this->assertTrue($this->blockEntity->get('revision_log')->access('view', $admin));
        $this->assertTrue($this->blockEntity->get('revision_log')->access('view', $editor));
        $this->assertFalse($this->blockEntity->get('revision_log')->access('view', $viewer));
    }

}
