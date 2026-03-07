<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides helpers for redirect destinations.
 */
class RedirectDestination implements RedirectDestinationInterface
{
    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * The destination used by the current request.
     *
     * @var string
     */
    protected $destination;

    /**
     * Constructs a new RedirectDestination instance.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
     *   The URL generator.
     */
    public function __construct(RequestStack $request_stack, protected \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator)
    {
        $this->requestStack = $request_stack;
    }

    /**
     * {@inheritdoc}
     */
    public function getAsArray(): array
    {
        return ['destination' => $this->get()];
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        if (!isset($this->destination)) {
            $query = $this->requestStack->getCurrentRequest()->query;
            if ($query->has('destination')) {
                $this->destination = $query->get('destination');
                if (UrlHelper::isExternal($this->destination)) {
                    // See https://www.drupal.org/node/2454955 for external redirects.
                    $this->destination = '/';
                }
            } else {
                $this->destination = $this->urlGenerator->generateFromRoute('<current>', [], ['query' => UrlHelper::filterQueryParameters($query->all())]);
            }
        }

        return $this->destination;
    }

    /**
     * {@inheritdoc}
     */
    public function set($new_destination): static
    {
        $this->destination = $new_destination;
        return $this;
    }

}
