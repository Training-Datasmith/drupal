<?php

declare(strict_types=1);

namespace Drupal\search\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search\Form\SearchBlockForm;

/**
 * Provides a 'Search form' block.
 */
#[Block(
    id: 'search_form_block',
    admin_label: new TranslatableMarkup('Search form'),
    category: new TranslatableMarkup('Forms'),
)]
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface
{
    /**
     * Constructs a new SearchLocalTask.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
     *   The form builder.
     * @param \Drupal\search\SearchPageRepositoryInterface $searchPageRepository
     *   The search page repository.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Form\FormBuilderInterface $formBuilder, protected \Drupal\search\SearchPageRepositoryInterface $searchPageRepository)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    protected function blockAccess(AccountInterface $account)
    {
        return AccessResult::allowedIfHasPermission($account, 'search content');
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $page = $this->configuration['page_id'] ?? null;
        return $this->formBuilder->getForm(SearchBlockForm::class, $page);
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration(): array
    {
        return [
          'page_id' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state): array
    {
        // The configuration for this block is which search page to connect the
        // form to. Options are all configured/active search pages.
        $options = [];
        $active_search_pages = $this->searchPageRepository->getActiveSearchPages();
        foreach ($this->searchPageRepository->sortSearchPages($active_search_pages) as $entity_id => $entity) {
            $options[$entity_id] = $entity->label();
        }

        $form['page_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Search page'),
          '#description' => $this->t('The search page that the form submits to, or Default for the default search page.'),
          '#default_value' => $this->configuration['page_id'],
          '#options' => $options,
          '#empty_option' => $this->t('Default'),
          '#empty_value' => '',
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state): void
    {
        // Handle the #empty_value: using the default requires specifying `null` in
        // the config.
        // @see search.schema.yml
        // @see \Drupal\search\Form\SearchBlockForm::buildForm()
        $this->configuration['page_id'] = $form_state->getValue('page_id') ?: null;
    }

}
