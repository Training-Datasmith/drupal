<?php

declare (strict_types=1);
namespace Drupal\Core\Authentication;

/**
 * A collector class for authentication providers.
 */
class Authentication_Collector implements Authentication_Collector_Interface
{
    /**
     * Array of all registered authentication providers, keyed by ID.
     *
     * @var \Drupal\Core\Authentication\AuthenticationProviderInterface[]
     */
    protected $providers;
    /**
     * Array of all providers and their priority.
     *
     * @var array
     */
    protected $provider_orders = [];
    /**
     * Sorted list of registered providers.
     *
     * @var \Drupal\Core\Authentication\AuthenticationProviderInterface[]
     */
    protected $sorted_providers;
    /**
     * List of providers which are allowed on routes with no _auth option.
     *
     * @var string[]
     */
    protected $global_providers;
    /**
     * {@inheritdoc}
     */
    public function add_provider(Authentication_Provider_Interface $provider, $provider_id, $priority = 0, $global = false): void
    {
        $this->providers[$provider_id] = $provider;
        $this->provider_orders[$priority][$provider_id] = $provider;
        // Force the providers to be re-sorted.
        $this->sorted_providers = null;
        if ($global) {
            $this->global_providers[$provider_id] = true;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function is_global($provider_id): bool
    {
        return isset($this->global_providers[$provider_id]);
    }
    /**
     * {@inheritdoc}
     */
    public function get_provider($provider_id)
    {
        return $this->providers[$provider_id] ?? null;
    }
    /**
     * {@inheritdoc}
     */
    public function get_sorted_providers()
    {
        if (!isset($this->sorted_providers)) {
            // Sort the providers according to priority.
            krsort($this->provider_orders);
            // Merge nested providers from $this->providers into
            // $this->sortedProviders.
            $this->sorted_providers = array_merge(...$this->provider_orders);
        }
        return $this->sorted_providers;
    }
}