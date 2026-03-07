<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\sort;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UncacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsSort;

/**
 * Handle a random sort.
 */
#[ViewsSort('random')]
class Random extends SortPluginBase implements CacheableDependencyInterface
{
    use UncacheableDependencyTrait;

    /**
     * {@inheritdoc}
     */
    public function usesGroupBy(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function query(): void
    {
        $this->query->addOrderBy('rand');
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state): void
    {
        parent::buildOptionsForm($form, $form_state);
        $form['order']['#access'] = false;
    }

}
