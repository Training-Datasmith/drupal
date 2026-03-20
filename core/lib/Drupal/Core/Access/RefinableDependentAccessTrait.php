<?php

declare (strict_types=1);
namespace Drupal\Core\Access;

/**
 * Trait for \Drupal\Core\Access\RefinableDependentAccessInterface.
 *
 * @internal
 */
trait Refinable_Dependent_Access_Trait
{
    /**
     * The access dependency.
     *
     * @var \Drupal\Core\Access\AccessibleInterface
     */
    protected $access_dependency;
    /**
     * {@inheritdoc}
     */
    public function set_access_dependency(Accessible_Interface $access_dependency)
    {
        $this->access_dependency = $access_dependency;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_access_dependency()
    {
        return $this->access_dependency;
    }
    /**
     * {@inheritdoc}
     */
    public function add_access_dependency(Accessible_Interface $access_dependency)
    {
        if (empty($this->access_dependency)) {
            $this->access_dependency = $access_dependency;
            return $this;
        }
        if (!$this->access_dependency instanceof Access_Group_And) {
            $access_group = new Access_Group_And();
            $this->access_dependency = $access_group->add_dependency($this->access_dependency);
        }
        $this->access_dependency->add_dependency($access_dependency);
        return $this;
    }
}