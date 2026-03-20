<?php

declare (strict_types=1);
namespace Drupal\Core\Condition;

use Drupal\Component\Plugin\Exception\Context_Exception;
/**
 * Resolves a set of conditions.
 */
trait Condition_Access_Resolver_Trait
{
    /**
     * Resolves the given conditions based on the condition logic ('and'/'or').
     *
     * @param \Drupal\Core\Condition\ConditionInterface[] $conditions
     *   A set of conditions.
     * @param string $condition_logic
     *   The logic used to compute access, either 'and' or 'or'.
     *
     * @return bool
     *   Whether these conditions grant or deny access.
     */
    protected function resolve_conditions($conditions, $condition_logic)
    {
        foreach ($conditions as $condition) {
            try {
                $pass = $condition->execute();
            } catch (Context_Exception) {
                // If a condition is missing context and is not negated, consider that a
                // fail.
                $pass = $condition->is_negated();
            }
            // If a condition fails and all conditions were needed, deny access.
            if (!$pass && $condition_logic == 'and') {
                return false;
            }
            // If a condition fails and all conditions were needed, deny access.
            if ($pass && $condition_logic == 'or') {
                return true;
            }
        }
        // Return TRUE if logic was 'and', meaning all rules passed.
        // Return FALSE if logic was 'or', meaning no rule passed.
        return $condition_logic == 'and';
    }
}