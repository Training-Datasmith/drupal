<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Annotation\Doctrine\Fixtures\Attribute;

// @phpstan-ignore attribute.notFound
#[NonexistentAttribute]
final class Nonexistent
{
}
