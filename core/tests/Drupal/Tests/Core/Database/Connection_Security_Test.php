<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database;

use Drupal\Core\Database\Connection;
use Drupal\Tests\Core\Database\Stub\StubConnection;
use Drupal\Tests\Core\Database\Stub\StubPDO;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests SQL injection protections in the database Connection class.
 *
 * These tests validate that the security fixes in Connection::preprocessStatement()
 * and the escape helpers prevent multi-statement injection, comment-based
 * injection, and identifier injection attacks.
 */
#[CoversClass(Connection::class)]
#[Group('Database')]
#[Group('Security')]
class Connection_Security_Test extends UnitTestCase
{
    /**
     * Creates a stub connection for testing.
     */
    protected function createConnection(string $prefix = ''): StubConnection
    {
        $mock_pdo = $this->createMock(StubPDO::class);
        return new StubConnection($mock_pdo, ['prefix' => $prefix]);
    }

    // -------------------------------------------------------------------------
    // Semicolon / multi-statement injection prevention
    // -------------------------------------------------------------------------

    /**
     * Verifies that a semicolon in a query raises an exception.
     *
     * Multi-statement injection (e.g. '; DROP TABLE users; --') is prevented
     * by rejecting any query string containing a semicolon unless the
     * 'allow_delimiter_in_query' option is explicitly set to TRUE.
     */
    public function test_semicolon_in_query_throws_exception_preventing_multi_statement_injection(): void
    {
        $connection = $this->createConnection();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('; is not supported in SQL strings');

        // This simulates what preprocessStatement() does when it detects a
        // semicolon in a query that should be single-statement only.
        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('preprocessStatement');

        $method->invoke($connection, 'SELECT 1; DROP TABLE users', ['allow_delimiter_in_query' => false]);
    }

    /**
     * Verifies that a trailing semicolon is silently trimmed (not treated as injection).
     *
     * A lone trailing semicolon is a common style artifact and should be
     * stripped rather than rejected.
     */
    public function test_trailing_semicolon_is_trimmed_not_rejected(): void
    {
        $connection = $this->createConnection();

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('preprocessStatement');

        // Should not throw — the semicolon is trailing whitespace.
        $result = $method->invoke(
            $connection,
            'SELECT 1   ;  ',
            ['allow_delimiter_in_query' => false, 'allow_square_brackets' => false]
        );

        $this->assertStringNotContainsString(';', $result);
    }

    /**
     * Verifies that allow_delimiter_in_query option permits semicolons.
     *
     * Stored procedure or function creation genuinely requires semicolons.
     * The option provides an explicit escape hatch for those cases.
     */
    public function test_semicolon_allowed_when_option_explicitly_set(): void
    {
        $connection = $this->createConnection();

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('preprocessStatement');

        // Should not throw when the option is explicitly set.
        $result = $method->invoke(
            $connection,
            'CREATE FUNCTION f() RETURNS INT BEGIN RETURN 1; END',
            ['allow_delimiter_in_query' => true, 'allow_square_brackets' => false]
        );

        $this->assertStringContainsString(';', $result);
    }

    // -------------------------------------------------------------------------
    // SQL comment injection prevention (filterComment / makeComment)
    // -------------------------------------------------------------------------

    /**
     * Verifies that asterisk-slash sequences in comments are neutralised.
     *
     * An attacker can terminate a SQL comment early using the */ sequence to
     * inject arbitrary SQL. The filterComment() method must sanitise this.
     */
    public function test_comment_injection_via_closing_comment_sequence_is_neutralised(): void
    {
        $connection = $this->createConnection();

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('filterComment');

        $malicious_comment = 'Exploit */ DROP TABLE node; --';
        $sanitised = $method->invoke($connection, $malicious_comment);

        // The closing comment marker must not appear verbatim.
        $this->assertStringNotContainsString('*/', $sanitised);
    }

    /**
     * Verifies that semicolons in comments are replaced with periods.
     *
     * A semicolon inside a SQL comment could be detected by the multi-statement
     * check. The filter converts them to periods to avoid false positives while
     * still neutralising potential injection vectors.
     */
    public function test_semicolons_in_comments_are_replaced_with_periods(): void
    {
        $connection = $this->createConnection();

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('filterComment');

        $comment_with_semicolons = 'End query; then do something bad';
        $sanitised = $method->invoke($connection, $comment_with_semicolons);

        $this->assertStringNotContainsString(';', $sanitised);
        $this->assertStringContainsString('.', $sanitised);
    }

    /**
     * Verifies that makeComment() wraps the sanitised comment correctly.
     */
    public function test_make_comment_wraps_and_sanitises_comment_array(): void
    {
        $connection = $this->createConnection();

        $result = $connection->makeComment(['Safe comment', 'Another * / injection attempt']);

        // Output must be a valid SQL comment.
        $this->assertStringStartsWith('/*', $result);
        $this->assertStringEndsWith('*/ ', $result);
        // The injection attempt must be neutralised.
        $this->assertStringNotContainsString('*/', substr($result, 2, -3));
    }

    // -------------------------------------------------------------------------
    // Identifier / table / field escaping
    // -------------------------------------------------------------------------

    /**
     * Verifies that escapeTable() strips characters that are not allowed in
     * table names, preventing identifier-based injection.
     */
    public function test_escape_table_strips_non_alphanumeric_characters(): void
    {
        $connection = $this->createConnection();

        // A table name containing SQL injection characters.
        $malicious = "users; DROP TABLE users --";
        $escaped = $connection->escapeTable($malicious);

        $this->assertStringNotContainsString(';', $escaped);
        $this->assertStringNotContainsString(' ', $escaped);
        $this->assertStringNotContainsString('-', $escaped);
        // Only alphanumerics, underscores, and dots are permitted.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_.]*$/', $escaped);
    }

    /**
     * Verifies that escapeField() strips injection characters from field names.
     */
    public function test_escape_field_strips_non_alphanumeric_characters(): void
    {
        $connection = $this->createConnection();

        $malicious_field = "title` UNION SELECT password FROM users--";
        $escaped = $connection->escapeField($malicious_field);

        // The dangerous characters must be stripped.
        $this->assertStringNotContainsString('`', $escaped);
        $this->assertStringNotContainsString('UNION', $escaped);
        $this->assertStringNotContainsString('-', $escaped);
    }

    /**
     * Verifies that escapeLike() protects wildcard characters in LIKE patterns.
     *
     * Without escaping, user-supplied values containing % or _ would act as
     * wildcards in LIKE queries, enabling information disclosure (e.g.
     * matching all rows when the user intends an exact match).
     */
    public function test_escape_like_neutralises_wildcard_characters(): void
    {
        $connection = $this->createConnection();

        $user_input = '100% organic _ free range';
        $escaped = $connection->escapeLike($user_input);

        $this->assertStringContainsString('\\%', $escaped);
        $this->assertStringContainsString('\\_', $escaped);
        // The escaped string should be safe to embed in a LIKE pattern.
        $this->assertStringNotContainsString('%', str_replace('\\%', '', $escaped));
    }

    // -------------------------------------------------------------------------
    // Array placeholder expansion (IN clause injection)
    // -------------------------------------------------------------------------

    /**
     * Verifies that a non-array value supplied for an array placeholder throws.
     *
     * If :ids[] is specified but a scalar is provided, the placeholder expansion
     * would silently produce malformed SQL. The exception prevents this.
     */
    public function test_array_placeholder_with_scalar_value_throws_exception(): void
    {
        $connection = $this->createConnection();

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('expandArguments');

        $query = 'SELECT * FROM {node} WHERE nid IN (:nids[])';
        $args  = [':nids[]' => 'not_an_array'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('can only be expanded with an array of values');

        $method->invokeArgs($connection, [&$query, &$args]);
    }

    /**
     * Verifies that a plain placeholder (without []) with an array value throws.
     *
     * Passing an array to a non-bracket placeholder would bypass the intended
     * expansion and could produce inconsistent or unsafe SQL.
     */
    public function test_non_array_placeholder_with_array_value_throws_exception(): void
    {
        $connection = $this->createConnection();

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('expandArguments');

        $query = 'SELECT * FROM {node} WHERE nid = :nid';
        $args  = [':nid' => [1, 2, 3]];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a trailing []');

        $method->invokeArgs($connection, [&$query, &$args]);
    }
}
