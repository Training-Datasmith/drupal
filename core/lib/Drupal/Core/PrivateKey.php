<?php

declare(strict_types=1);

namespace Drupal\Core;

use Drupal\Component\Utility\Crypt;

/**
 * Manages the Drupal private key.
 */
class PrivateKey
{
    /**
     * Constructs the private key object.
     *
     * @param \Drupal\Core\State\StateInterface $state
     *   The state service.
     */
    public function __construct(protected \Drupal\Core\State\StateInterface $state)
    {
    }

    /**
     * Gets the private key.
     *
     * @return string
     *   The private key.
     */
    public function get()
    {
        if (!$key = $this->state->get('system.private_key')) {
            $key = $this->create();
            $this->set($key);
        }

        return $key;
    }

    /**
     * Sets the private key.
     *
     * @param string $key
     *   The private key to set.
     */
    public function set(#[\SensitiveParameter] $key)
    {
        return $this->state->set('system.private_key', $key);
    }

    /**
     * Creates a new private key.
     *
     * @return string
     *   The private key.
     */
    protected function create(): string
    {
        return Crypt::randomBytesBase64(55);
    }

}
