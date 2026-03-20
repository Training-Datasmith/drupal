<?php

declare(strict_types=1);

namespace Drupal\node\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides upcasting for a node entity in preview.
 */
class NodePreviewConverter implements ParamConverterInterface
{
    /**
     * Constructs a new NodePreviewConverter.
     *
     * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
     *   The factory for the temp store object.
     */
    public function __construct(protected \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function convert($value, $definition, $name, array $defaults)
    {
        $store = $this->tempStoreFactory->get('node_preview');
        if ($form_state = $store->get($value)) {
            return $form_state->getFormObject()->getEntity();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function applies($definition, $name, Route $route): bool
    {
        if (!empty($definition['type']) && $definition['type'] == 'node_preview') {
            return true;
        }
        return false;
    }

}
