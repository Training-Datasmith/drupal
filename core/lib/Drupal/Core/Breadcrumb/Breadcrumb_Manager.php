<?php

declare (strict_types=1);
namespace Drupal\Core\Breadcrumb;

use Drupal\Core\Cache\Cacheable_Metadata;
use Drupal\Core\Routing\Route_Match_Interface;
/**
 * Provides a breadcrumb manager.
 *
 * Can be assigned any number of BreadcrumbBuilderInterface objects by calling
 * the addBuilder() method. When build() is called it iterates over the objects
 * in priority order and uses the first one that returns TRUE from
 * BreadcrumbBuilderInterface::applies() to build the breadcrumbs.
 *
 * @see \Drupal\Core\DependencyInjection\Compiler\RegisterBreadcrumbBuilderPass
 */
class Breadcrumb_Manager implements Chain_Breadcrumb_Builder_Interface
{
    /**
     * Holds arrays of breadcrumb builders, keyed by priority.
     *
     * @var array
     */
    protected $builders = [];
    /**
     * Holds the array of breadcrumb builders sorted by priority.
     *
     * Set to NULL if the array needs to be re-calculated.
     *
     * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface[]|null
     */
    protected $sorted_builders;
    /**
     * Constructs a \Drupal\Core\Breadcrumb\BreadcrumbManager object.
     *
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     */
    public function __construct(protected \Drupal\Core\Extension\Module_Handler_Interface $module_handler)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function add_builder(Breadcrumb_Builder_Interface $builder, $priority): void
    {
        $this->builders[$priority][] = $builder;
        // Force the builders to be re-sorted.
        $this->sorted_builders = null;
    }
    /**
     * {@inheritdoc}
     */
    public function applies(Route_Match_Interface $route_match, Cacheable_Metadata $cacheable_metadata): bool
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function build(Route_Match_Interface $route_match)
    {
        $cacheable_metadata = new Cacheable_Metadata();
        $breadcrumb = new Breadcrumb();
        $context = ['builder' => null];
        // Call the build method of registered breadcrumb builders,
        // until one of them returns an array.
        foreach ($this->get_sorted_builders() as $builder) {
            if (!$builder->applies($route_match, $cacheable_metadata)) {
                // The builder does not apply, so we continue with the other builders.
                continue;
            }
            $breadcrumb = $builder->build($route_match);
            if ($breadcrumb instanceof Breadcrumb) {
                $context['builder'] = $builder;
                break;
            } else {
                throw new \UnexpectedValueException('Invalid breadcrumb returned by ' . $builder::class . '::build().');
            }
        }
        // Ensure all collected cacheability is applied.
        $breadcrumb->add_cacheable_dependency($cacheable_metadata);
        // Allow modules to alter the breadcrumb.
        $this->module_handler->alter('system_breadcrumb', $breadcrumb, $route_match, $context);
        return $breadcrumb;
    }
    /**
     * Returns the sorted array of breadcrumb builders.
     *
     * @return \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface[]
     *   An array of breadcrumb builder objects.
     */
    protected function get_sorted_builders()
    {
        if (!isset($this->sorted_builders)) {
            // Sort the builders according to priority.
            krsort($this->builders);
            // Merge nested builders from $this->builders into $this->sortedBuilders.
            $this->sorted_builders = array_merge(...$this->builders);
        }
        return $this->sorted_builders;
    }
}