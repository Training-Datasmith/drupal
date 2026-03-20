<?php

declare (strict_types=1);
namespace Drupal\Core\Database;

/**
 * Exception thrown if a SELECT query trying to execute rowCount() on result.
 */
class Row_Count_Exception extends \RuntimeException implements Database_Exception
{
    public function __construct($message = '', $code = 0, ?\Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'rowCount() is supported for DELETE, INSERT, or UPDATE statements performed with structured query builders only, since they would not be portable across database engines otherwise. If the query builders are not sufficient, use a prepareStatement() with an $allow_row_count argument set to TRUE, execute() the Statement and get the number of matched rows via rowCount().';
        }
        parent::__construct($message, $code, $previous);
    }
}