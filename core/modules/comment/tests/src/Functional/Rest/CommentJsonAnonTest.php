<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Rest;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Comment Json Anon.
 */
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class CommentJsonAnonTest extends CommentResourceTestBase
{
    use AnonResourceTestTrait;

    /**
     * {@inheritdoc}
     */
    protected static $format = 'json';

    /**
     * {@inheritdoc}
     */
    protected static $mimeType = 'application/json';

    /**
     * {@inheritdoc}
     */
    protected $defaultTheme = 'stark';

    /**
     * {@inheritdoc}
     *
     * Anonymous users cannot edit their own comments.
     *
     * @see \Drupal\comment\CommentAccessControlHandler::checkAccess
     *
     * Therefore we grant them the 'administer comments' permission for the
     * purpose of this test.
     *
     * @see ::setUpAuthorization
     */
    protected static $patchProtectedFieldNames = [
      'pid' => null,
      'entity_id' => null,
      'changed' => null,
      'thread' => null,
      'entity_type' => null,
      'field_name' => null,
    ];

}
