<?php

declare (strict_types=1);
namespace Drupal\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Container as SymfonyContainer;
use Symfony\Component\Dependency_Injection\Container_Interface as SymfonyContainerInterface;
/**
 * Retrieves service IDs from the container for public services.
 *
 * Heavily inspired by \Symfony\Component\DependencyInjection\ReverseContainer.
 */
final class Reverse_Container
{
    /**
     * A closure on the container that can search for services.
     */
    private readonly \Closure $get_service_id;
    /**
     * A static map of services to a hash.
     */
    private static array $recorded_services = [];
    /**
     * Constructs a ReverseContainer object.
     *
     * @param \Drupal\Component\DependencyInjection\Container|\Symfony\Component\DependencyInjection\Container $serviceContainer
     *   The service container.
     */
    public function __construct(private readonly Container|Symfony_Container $service_container)
    {
        $this->get_service_id = \Closure::bind(fn($service): ?string => array_search($service, $this->services, true) ?: null, $service_container, $service_container);
    }
    /**
     * Returns the ID of the passed object when it exists as a service.
     *
     * To be reversible, services need to be public.
     *
     * @param object $service
     *   The service to find the ID for.
     */
    public function get_id(object $service): ?string
    {
        if ($this->service_container === $service || $service instanceof Symfony_Container_Interface) {
            return 'service_container';
        }
        $hash = $this->generate_service_id_hash($service);
        $id = self::$recorded_services[$hash] ?? ($this->get_service_id)($service);
        if ($id !== null && $this->service_container->has($id)) {
            self::$recorded_services[$hash] = $id;
            return $id;
        }
        return null;
    }
    /**
     * Records a map of the container's services.
     *
     * This method is used so that stale services can be serialized after a
     * container has been re-initialized.
     */
    public function record_container(): void
    {
        $service_recorder = \Closure::bind(fn(): array => $this->services, $this->service_container, $this->service_container);
        self::$recorded_services = array_merge(self::$recorded_services, array_flip(array_map($this->generate_service_id_hash(...), $service_recorder())));
    }
    /**
     * Generates an identifier for a service based on the object class and hash.
     *
     * @param object $object
     *   The object to generate an identifier for.
     *
     * @return string
     *   The object's class and hash concatenated together.
     */
    private function generate_service_id_hash(object $object): string
    {
        // Include class name as an additional namespace for the hash since
        // spl_object_hash's return can be recycled. This still is not a 100%
        // guarantee to be unique but makes collisions incredibly difficult and even
        // then the interface would be preserved.
        // @see https://php.net/spl_object_hash#refsect1-function.spl-object-hash-notes
        return $object::class . spl_object_hash($object);
    }
}