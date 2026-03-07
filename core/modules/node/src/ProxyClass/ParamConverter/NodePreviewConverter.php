<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\node\ParamConverter\NodePreviewConverter' "core/modules/node/src".
 */

namespace Drupal\node\ProxyClass\ParamConverter {

    use Drupal\Core\ParamConverter\ParamConverterInterface;
    use Drupal\Core\DependencyInjection\DependencySerializationTrait;
    use Symfony\Component\DependencyInjection\ContainerInterface;
    use Symfony\Component\Routing\Route;

    /**
     * Provides a proxy class for \Drupal\node\ParamConverter\NodePreviewConverter.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class NodePreviewConverter implements ParamConverterInterface
    {

        use DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\node\ParamConverter\NodePreviewConverter
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupalProxyOriginalServiceId
         *   The service ID of the original service.
         */
        public function __construct(ContainerInterface $container, /**
         * The id of the original proxied service.
         */
        protected $drupalProxyOriginalServiceId)
        {
            $this->container = $container;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         */
        public function convert($value, $definition, $name, array $defaults)
        {
            return $this->lazyLoadItself()->convert($value, $definition, $name, $defaults);
        }

        /**
         * {@inheritdoc}
         */
        public function applies($definition, $name, Route $route)
        {
            return $this->lazyLoadItself()->applies($definition, $name, $route);
        }

    }

}
