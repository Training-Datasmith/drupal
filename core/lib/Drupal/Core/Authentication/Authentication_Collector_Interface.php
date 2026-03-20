<?php

declare (strict_types=1);
namespace Drupal\Core\Authentication;

/**
 * Interface for collectors of registered authentication providers.
 */
interface Authentication_Collector_Interface
{
    /**
     * Adds a provider to the array of registered providers.
     *
     * @param \Drupal\Core\Authentication\AuthenticationProviderInterface $provider
     *   The provider object.
     * @param string $provider_id
     *   Identifier of the provider.
     * @param int $priority
     *   (optional) The provider's priority.
     * @param bool $global
     *   (optional) TRUE if the provider is to be applied globally on all routes.
     *   Defaults to FALSE.
     */
    public function add_provider(Authentication_Provider_Interface $provider, $provider_id, $priority = 0, $global = false);
    /**
     * Returns whether a provider is considered global.
     *
     * @param string $provider_id
     *   The provider ID.
     *
     * @return bool
     *   TRUE if the provider is global, FALSE otherwise.
     *
     * @see \Drupal\Core\Authentication\AuthenticationCollectorInterface::addProvider
     */
    public function is_global($provider_id);
    /**
     * Returns an authentication provider.
     *
     * @param string $provider_id
     *   The provider ID.
     *
     * @return \Drupal\Core\Authentication\AuthenticationProviderInterface|null
     *   The authentication provider which matches the ID.
     */
    public function get_provider(string $provider_id);
    /**
     * Returns the sorted array of authentication providers.
     *
     * @return \Drupal\Core\Authentication\AuthenticationProviderInterface[]
     *   An array of authentication provider objects.
     */
    public function get_sorted_providers();
}