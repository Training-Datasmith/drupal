<?php

declare (strict_types=1);
namespace Drupal\Core\Database\Event;

/**
 * Enumeration of the statement related database events.
 */
enum Statement_Event : string
{
    case ExecutionStart = Statement_Execution_Start_Event::class;
    case ExecutionEnd = Statement_Execution_End_Event::class;
    case ExecutionFailure = Statement_Execution_Failure_Event::class;
    /**
     * Returns an array with all statement related events.
     *
     * @return list<class-string<\Drupal\Core\Database\Event\DatabaseEvent>>
     *   An array with all statement related events.
     */
    public static function all(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }
}