<?php

declare(strict_types=1);

namespace Drupal\Core\Routing;

/**
 * Provides a redirect response which contains trusted URLs.
 *
 * Use this class in case you know that you want to redirect to an external URL.
 */
class TrustedRedirectResponse extends CacheableSecuredRedirectResponse
{
    use LocalAwareRedirectResponseTrait;

    /**
     * A list of trusted URLs, which are safe to redirect to.
     *
     * @var string[]
     */
    protected $trustedUrls = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($url)
    {
        $this->trustedUrls[$url] = true;
    }

    /**
     * Sets the target URL to a trusted URL.
     *
     * @param string $url
     *   A trusted URL.
     *
     * @return $this
     */
    public function setTrustedTargetUrl($url)
    {
        $this->trustedUrls[$url] = true;
        return $this->setTargetUrl($url);
    }

    /**
     * {@inheritdoc}
     */
    protected function isSafe($url): bool
    {
        return !empty($this->trustedUrls[$url]) || $this->isLocal($url);
    }

}
