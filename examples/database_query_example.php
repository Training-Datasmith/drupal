<?php

declare(strict_types=1);

/**
 * @file
 * Practical examples of Drupal's Database Abstraction Layer.
 *
 * These examples show how to use \Drupal\Core\Database\Connection
 * for common CRUD operations using the query-builder API.
 *
 * In real Drupal code, obtain the connection via dependency injection:
 *   public function __construct(protected Connection $database) {}
 * or via the service container in procedural code:
 *   $database = \Drupal::database();
 */

use Drupal\Core\Database\Database;

// ---------------------------------------------------------------------------
// Example 1: SELECT query with conditions and ordering
// ---------------------------------------------------------------------------

$database = Database::getConnection();

// Fetch all published nodes of type 'article', ordered by creation date.
$result = $database->select('node_field_data', 'n')
    ->fields('n', ['nid', 'title', 'created'])
    ->condition('n.status', 1)
    ->condition('n.type', 'article')
    ->orderBy('n.created', 'DESC')
    ->range(0, 10)
    ->execute();

foreach ($result as $row) {
    printf("NID %d: %s (created %s)\n", $row->nid, $row->title, date('Y-m-d', $row->created));
}

// ---------------------------------------------------------------------------
// Example 2: INSERT query
// ---------------------------------------------------------------------------

$database->insert('my_module_data')
    ->fields([
        'uid'     => 42,
        'data'    => json_encode(['key' => 'value']),
        'created' => \Drupal::time()->getRequestTime(),
    ])
    ->execute();

$inserted_id = $database->lastInsertId();
echo "Inserted row with ID: {$inserted_id}\n";

// ---------------------------------------------------------------------------
// Example 3: UPDATE with condition
// ---------------------------------------------------------------------------

$updated = $database->update('my_module_data')
    ->fields(['data' => json_encode(['key' => 'updated_value'])])
    ->condition('uid', 42)
    ->execute();

echo "Rows updated: {$updated}\n";

// ---------------------------------------------------------------------------
// Example 4: DELETE with condition
// ---------------------------------------------------------------------------

$deleted = $database->delete('my_module_data')
    ->condition('uid', 42)
    ->execute();

echo "Rows deleted: {$deleted}\n";

// ---------------------------------------------------------------------------
// Example 5: Parameterized raw query (avoid SQL injection)
// ---------------------------------------------------------------------------

// Use placeholders (:name) for all user-supplied values.
$result = $database->query(
    'SELECT nid, title FROM {node_field_data} WHERE type = :type AND status = :status',
    [':type' => 'article', ':status' => 1]
);

foreach ($result as $row) {
    echo "Article: {$row->title}\n";
}

// ---------------------------------------------------------------------------
// Example 6: IN clause with array placeholder expansion
// ---------------------------------------------------------------------------

$nids = [1, 2, 3, 4, 5];

$result = $database->query(
    'SELECT nid, title FROM {node_field_data} WHERE nid IN (:nids[])',
    [':nids[]' => $nids]
);

foreach ($result as $row) {
    echo "Node {$row->nid}: {$row->title}\n";
}

// ---------------------------------------------------------------------------
// Example 7: Transaction with savepoint
// ---------------------------------------------------------------------------

$transaction = $database->startTransaction('my_savepoint');

try {
    $database->insert('my_module_log')
        ->fields(['message' => 'Starting operation', 'timestamp' => time()])
        ->execute();

    $database->insert('my_module_log')
        ->fields(['message' => 'Operation complete', 'timestamp' => time()])
        ->execute();

    // Commit by allowing $transaction to go out of scope.
    unset($transaction);
} catch (\Exception $e) {
    // Rolling back automatically happens when $transaction is destroyed
    // inside a catch block without an explicit commit.
    unset($transaction);
    throw $e;
}
