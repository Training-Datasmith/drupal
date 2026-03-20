<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

/**
 * Resolves the dependencies of asset (CSS/JavaScript) libraries.
 */
class Library_Dependency_Resolver implements Library_Dependency_Resolver_Interface
{
    /**
     * The libraries dependencies.
     *
     * @var array
     */
    protected $libraries_dependencies = [];
    /**
     * Constructs a new LibraryDependencyResolver instance.
     *
     * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
     *   The library discovery service.
     */
    public function __construct(protected \Drupal\Core\Asset\Library_Discovery_Interface $library_discovery)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_libraries_with_dependencies(array $libraries): array
    {
        $return = [];
        foreach ($libraries as $library) {
            if (!isset($this->libraries_dependencies[$library])) {
                $this->libraries_dependencies[$library] = $this->do_get_dependencies([$library]);
            }
            $return += $this->libraries_dependencies[$library];
        }
        return array_values($return);
    }
    /**
     * Gets the given libraries with its dependencies.
     *
     * Helper method for ::getLibrariesWithDependencies().
     *
     * @param string[] $libraries_with_unresolved_dependencies
     *   A list of libraries, with unresolved dependencies, in the order they
     *   should be loaded.
     * @param string[] $final_libraries
     *   The final list of libraries (the return value) that is being built
     *   recursively.
     *
     * @return string[]
     *   A list of libraries, in the order they should be loaded, including their
     *   dependencies.
     */
    protected function do_get_dependencies(array $libraries_with_unresolved_dependencies, array $final_libraries = [])
    {
        foreach ($libraries_with_unresolved_dependencies as $library) {
            if (!isset($final_libraries[$library])) {
                [$extension, $name] = explode('/', $library, 2);
                $definition = $this->library_discovery->get_library_by_name($extension, $name);
                if (!empty($definition['dependencies'])) {
                    $final_libraries = $this->do_get_dependencies($definition['dependencies'], $final_libraries);
                }
                $final_libraries[$library] = $library;
            }
        }
        return $final_libraries;
    }
    /**
     * {@inheritdoc}
     */
    public function get_minimal_representative_subset(array $libraries): array
    {
        assert(count($libraries) === count(array_unique($libraries)), '$libraries can\'t contain duplicate items.');
        // Determine each library's dependencies.
        $all_dependencies = [];
        foreach ($libraries as $library) {
            $with_deps = $this->get_libraries_with_dependencies([$library]);
            // We don't need library itself listed in the dependencies.
            $all_dependencies = array_unique(array_merge($all_dependencies, array_diff($with_deps, [$library])));
        }
        return array_values(array_diff($libraries, array_intersect($all_dependencies, $libraries)));
    }
}