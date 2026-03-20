<?php

declare (strict_types=1);
namespace Drupal\Core\Default_Content;

use Drupal\Core\Entity\Entity_Constraint_Violation_List_Interface;
use Symfony\Component\Validator\Constraint_Violation_Interface;
/**
 * Thrown if an entity being imported has validation errors.
 *
 * @internal
 *   This API is experimental.
 */
final class Invalid_Entity_Exception extends \RuntimeException
{
    public function __construct(public readonly Entity_Constraint_Violation_List_Interface $violations, public readonly string $file_path)
    {
        $messages = [];
        foreach ($violations as $violation) {
            assert($violation instanceof Constraint_Violation_Interface);
            $messages[] = $violation->get_property_path() . '=' . $violation->get_message();
        }
        // Example: "/path/to/file.yml: field_a=Violation 1., field_b=Violation 2.".
        parent::__construct("{$file_path}: " . implode('||', $messages));
    }
}