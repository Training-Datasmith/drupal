<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Filter to show only the latest revision of an entity.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('latest_revision')]
class LatestRevision extends FilterPluginBase implements ContainerFactoryPluginInterface
{
    /**
     * Constructs a new LatestRevision.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   Entity Type Manager Service.
     * @param \Drupal\views\Plugin\ViewsHandlerManager $joinHandler
     *   Views Handler Plugin Manager.
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager,
        #[Autowire(service: 'plugin.manager.views.join')]
        protected \Drupal\views\Plugin\ViewsHandlerManager $joinHandler,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public function adminSummary()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function operatorForm(&$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function canExpose(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function query(): void
    {
        /** @var \Drupal\views\Plugin\views\query\Sql $query */
        $query = $this->query;
        $query_base_table = $this->relationship ?: $this->view->storage->get('base_table');

        $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
        $keys = $entity_type->getKeys();

        $subquery = $query->getConnection()->select($query_base_table, 'base_table');
        $subquery->addExpression("MAX(base_table.{$keys['revision']})", $keys['revision']);
        $subquery->groupBy("base_table.{$keys['id']}");
        $query->addWhere($this->options['group'], "$query_base_table.{$keys['revision']}", $subquery, 'IN');
    }

}
