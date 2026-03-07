<?php

declare(strict_types=1);

namespace Drupal\Core\Access;

use Drupal\Core\Session\AccountInterface;

/**
 * An access group where all the dependencies must be allowed.
 *
 * @internal
 */
class AccessGroupAnd implements AccessibleInterface
{
    /**
     * The access dependencies.
     *
     * @var \Drupal\Core\Access\AccessibleInterface[]
     */
    protected $dependencies = [];

    /**
     * Adds an access dependency.
     *
     * @param \Drupal\Core\Access\AccessibleInterface $dependency
     *   The access dependency to be added.
     *
     * @return $this
     */
    public function addDependency(AccessibleInterface $dependency): static
    {
        $this->dependencies[] = $dependency;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function access($operation, ?AccountInterface $account = null, $return_as_object = false)
    {
        $access_result = AccessResult::neutral();
        foreach (array_slice($this->dependencies, 1) as $dependency) {
            $access_result = $access_result->andIf($dependency->access($operation, $account, true));
        }
        return $return_as_object ? $access_result : $access_result->isAllowed();
    }

    /**
     * Gets all the access dependencies.
     *
     * @return list<\Drupal\Core\Access\AccessibleInterface>
     *   The list of access dependencies.
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

}
