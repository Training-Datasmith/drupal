<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

/**
 * Defines what to do if importing an entity that already exists (by UUID).
 *
 * @internal
 *   This API is experimental.
 */
enum Existing
{
    case Error;
    case Skip;
}