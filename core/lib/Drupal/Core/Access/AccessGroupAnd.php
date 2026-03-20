<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

use Drupal\Core\Session\Account_Interface;
/**
 * An access group where all the dependencies must be allowed.
 *
 * @internal
 */
class Access_Group_And implements Accessible_Interface
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
    public function add_dependency(Accessible_Interface $dependency): static
    {
        $this->dependencies[] = $dependency;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function access($operation, ?Account_Interface $account = null, $return_as_object = false)
    {
        $access_result = Access_Result::neutral();
        foreach (array_slice($this->dependencies, 1) as $dependency) {
            $access_result = $access_result->and_if($dependency->access($operation, $account, true));
        }
        return $return_as_object ? $access_result : $access_result->is_allowed();
    }
    /**
     * Gets all the access dependencies.
     *
     * @return list<\Drupal\Core\Access\AccessibleInterface>
     *   The list of access dependencies.
     */
    public function get_dependencies()
    {
        return $this->dependencies;
    }
}