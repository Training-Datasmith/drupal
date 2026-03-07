<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\sort;

use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views\ViewExecutable;

/**
 * Handler for GROUP BY on simple numeric fields.
 */
#[ViewsSort('groupby_numeric')]
class GroupByNumeric extends SortPluginBase
{
    /**
     * The original handler.
     */
    protected HandlerBase $handler;

    /**
     * {@inheritdoc}
     */
    public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = null): void
    {
        parent::init($view, $display, $options);

        // Initialize the original handler.
        $this->handler = \Drupal::service('plugin.manager.views.sort')->getHandler($options);
        $this->handler->init($view, $display, $options);
    }

    /**
     * Called to add the field to a query.
     */
    public function query(): void
    {
        $this->ensureMyTable();

        $params = [
          'function' => $this->options['group_type'],
        ];

        $this->query->addOrderBy($this->tableAlias, $this->realField, $this->options['order'], null, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function adminLabel($short = false)
    {
        return $this->getField(parent::adminLabel($short));
    }

}
