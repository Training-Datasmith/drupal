<?php

declare(strict_types=1);

/**
 * @file
 * Practical examples of Drupal's Entity API.
 *
 * Demonstrates loading, creating, updating, translating, and validating
 * content entities using \Drupal\Core\Entity\ContentEntityBase subclasses.
 *
 * In real Drupal code, inject \Drupal\Core\Entity\EntityTypeManagerInterface
 * rather than using static \Drupal:: calls.
 */

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

// ---------------------------------------------------------------------------
// Example 1: Load a node by ID
// ---------------------------------------------------------------------------

/** @var \Drupal\node\NodeInterface $node */
$node = Node::load(1);

if ($node !== null) {
    printf(
        "Node %d: '%s' (type: %s, published: %s)\n",
        $node->id(),
        $node->getTitle(),
        $node->bundle(),
        $node->isPublished() ? 'yes' : 'no'
    );
}

// ---------------------------------------------------------------------------
// Example 2: Create and save a new node
// ---------------------------------------------------------------------------

$node = Node::create([
    'type'   => 'article',
    'title'  => 'My Example Article',
    'status' => 1,
    'uid'    => 1,
    'body'   => [
        'value'  => '<p>This is the body text.</p>',
        'format' => 'basic_html',
    ],
]);

// Validate before saving (optional but recommended for programmatic creates).
$violations = $node->validate();
if ($violations->count() > 0) {
    foreach ($violations as $violation) {
        printf("Validation error on '%s': %s\n", $violation->getPropertyPath(), $violation->getMessage());
    }
} else {
    $node->save();
    printf("Created node NID %d\n", $node->id());
}

// ---------------------------------------------------------------------------
// Example 3: Update a field value and save a new revision
// ---------------------------------------------------------------------------

/** @var \Drupal\node\NodeInterface $node */
$node = Node::load(1);

if ($node !== null) {
    $node->setTitle('Updated Title');
    $node->setNewRevision(true);
    $node->revision_log->value = 'Updated title via API';
    $node->save();

    printf("New revision ID: %d\n", $node->getRevisionId());
}

// ---------------------------------------------------------------------------
// Example 4: Work with multilingual translations
// ---------------------------------------------------------------------------

/** @var \Drupal\node\NodeInterface $node */
$node = Node::load(1);

if ($node !== null && $node->isTranslatable()) {
    if ($node->hasTranslation('fr')) {
        $french = $node->getTranslation('fr');
        printf("French title: %s\n", $french->getTitle());
    } else {
        $french = $node->addTranslation('fr', [
            'title' => 'Mon Article',
            'status' => 1,
        ]);
        $french->save();
        printf("Added French translation\n");
    }
}

// ---------------------------------------------------------------------------
// Example 5: Entity query — find nodes by field value
// ---------------------------------------------------------------------------

/** @var EntityTypeManagerInterface $entity_type_manager */
$entity_type_manager = \Drupal::entityTypeManager();

$nids = $entity_type_manager->getStorage('node')
    ->getQuery()
    ->accessCheck(true)
    ->condition('type', 'article')
    ->condition('status', 1)
    ->sort('created', 'DESC')
    ->range(0, 5)
    ->execute();

$nodes = Node::loadMultiple($nids);

foreach ($nodes as $node) {
    printf("  - [%d] %s\n", $node->id(), $node->getTitle());
}

// ---------------------------------------------------------------------------
// Example 6: Load a user and check capability
// ---------------------------------------------------------------------------

/** @var \Drupal\user\UserInterface $user */
$user = User::load(1);

if ($user !== null) {
    printf(
        "User '%s' can administer nodes: %s\n",
        $user->getDisplayName(),
        $user->hasPermission('administer nodes') ? 'yes' : 'no'
    );
}
