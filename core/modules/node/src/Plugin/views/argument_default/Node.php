<?php

declare(strict_types=1);

namespace Drupal\node\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Default argument plugin to extract a node.
 */
#[ViewsArgumentDefault(
    id: 'node',
    title: new TranslatableMarkup('Content ID from URL'),
)]
class Node extends ArgumentDefaultPluginBase implements CacheableDependencyInterface
{
    /**
     * Constructs a new Node instance.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
     *   The route match.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Routing\RouteMatchInterface $routeMatch)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public function getArgument()
    {
        // Get the node object from current route.
        $node = $this->routeMatch->getParameter('node') ?? $this->routeMatch->getParameter('node_preview');
        if ($node instanceof NodeInterface) {
            return $node->id();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheMaxAge(): int
    {
        return Cache::PERMANENT;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts(): array
    {
        return ['url'];
    }

}
