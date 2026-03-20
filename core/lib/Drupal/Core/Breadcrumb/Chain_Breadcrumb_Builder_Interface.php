<?php

declare (strict_types=1);
namespace Drupal\Core\Breadcrumb;

/**
 * Defines an interface a chained service that builds the breadcrumb.
 */
interface Chain_Breadcrumb_Builder_Interface extends Breadcrumb_Builder_Interface
{
    /**
     * Adds another breadcrumb builder.
     *
     * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $builder
     *   The breadcrumb builder to add.
     * @param int $priority
     *   Priority of the breadcrumb builder.
     */
    public function add_builder(Breadcrumb_Builder_Interface $builder, $priority);
}