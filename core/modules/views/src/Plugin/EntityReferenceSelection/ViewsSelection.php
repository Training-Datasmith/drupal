<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'selection' entity_reference.
 */
#[EntityReferenceSelection(
    id: 'views',
    label: new TranslatableMarkup('Views: Filter by an entity reference view'),
    group: 'views',
    weight: 0
)]
class ViewsSelection extends SelectionPluginBase implements ContainerFactoryPluginInterface
{
    use StringTranslationTrait;

    /**
     * The loaded View object.
     *
     * @var \Drupal\views\ViewExecutable
     */
    protected $view;

    /**
     * Constructs a new ViewsSelection object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager service.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler service.
     * @param \Drupal\Core\Session\AccountInterface $currentUser
     *   The current user.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager, protected \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler, protected \Drupal\Core\Session\AccountInterface $currentUser, protected \Drupal\Core\Render\RendererInterface $renderer)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration(): array
    {
        return [
          'view' => [
            'view_name' => null,
            'display_name' => null,
            'arguments' => [],
          ],
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $view_settings = $this->getConfiguration()['view'];
        $displays = Views::getApplicableViews('entity_reference_display');
        // Filter views that list the entity type we want, and group the separate
        // displays by view.
        $entity_type = $this->entityTypeManager->getDefinition($this->configuration['target_type']);
        $view_storage = $this->entityTypeManager->getStorage('view');

        $options = [];
        foreach ($displays as $data) {
            [$view_id, $display_id] = $data;
            $view = $view_storage->load($view_id);
            if (in_array($view->get('base_table'), [$entity_type->getBaseTable(), $entity_type->getDataTable()])) {
                $display = $view->get('display');
                $options[$view_id . ':' . $display_id] = $view_id . ' - ' . $display[$display_id]['display_title'];
            }
        }

        // The value of the 'view_and_display' select below will need to be split
        // into 'view_name' and 'view_display' in the final submitted values, so
        // we massage the data at validate time on the wrapping element (not
        // ideal).
        $form['view']['#element_validate'] = [[static::class, 'settingsFormValidate']];

        if ($options) {
            $default = !empty($view_settings['view_name']) ? $view_settings['view_name'] . ':' . $view_settings['display_name'] : null;
            $form['view']['view_and_display'] = [
              '#type' => 'select',
              '#title' => $this->t('View used to select the entities'),
              '#required' => true,
              '#options' => $options,
              '#default_value' => $default,
              '#description' => '<p>' . $this->t('Choose the view and display that select the entities that can be referenced.<br />Only views with a display of type "Entity Reference" are eligible.') . '</p>',
            ];

            $default = !empty($view_settings['arguments']) ? implode(', ', $view_settings['arguments']) : '';
            $form['view']['arguments'] = [
              '#type' => 'textfield',
              '#title' => $this->t('View arguments'),
              '#default_value' => $default,
              '#required' => false,
              '#description' => $this->t('Provide a comma separated list of arguments to pass to the view.'),
            ];
        } else {
            if ($this->currentUser->hasPermission('administer views') && $this->moduleHandler->moduleExists('views_ui')) {
                $form['view']['no_view_help'] = [
                  '#markup' => '<p>' . $this->t('No eligible views were found. <a href=":create">Create a view</a> with an <em>Entity Reference</em> display, or add such a display to an <a href=":existing">existing view</a>.', [
                    ':create' => Url::fromRoute('views_ui.add')->toString(),
                    ':existing' => Url::fromRoute('entity.view.collection')->toString(),
                  ]) . '</p>',
                ];
            } else {
                $form['view']['no_view_help']['#markup'] = '<p>' . $this->t('No eligible views were found.') . '</p>';
            }
        }
        return $form;
    }

    /**
     * Initializes a view.
     *
     * @param string|null $match
     *   (Optional) Text to match the label against. Defaults to NULL.
     * @param string $match_operator
     *   (Optional) The operation the matching should be done with. Defaults
     *   to "CONTAINS".
     * @param int $limit
     *   Limit the query to a given number of items. Defaults to 0, which
     *   indicates no limiting.
     * @param array|null $ids
     *   Array of entity IDs. Defaults to NULL.
     *
     * @return bool
     *   Return TRUE if the view was initialized, FALSE otherwise.
     */
    protected function initializeView($match = null, $match_operator = 'CONTAINS', $limit = 0, $ids = null): bool
    {
        $view_name = $this->getConfiguration()['view']['view_name'];
        $display_name = $this->getConfiguration()['view']['display_name'];

        // Check that the view is valid and the display still exists.
        $this->view = Views::getView($view_name);
        if (!$this->view || !$this->view->access($display_name)) {
            \Drupal::messenger()->addWarning($this->t('The reference view %view_name cannot be found.', ['%view_name' => $view_name]));
            return false;
        }
        $this->view->setDisplay($display_name);

        // Pass options to the display handler to make them available later.
        $entity_reference_options = [
          'match' => $match,
          'match_operator' => $match_operator,
          'limit' => $limit,
          'ids' => $ids,
        ];
        $this->view->displayHandlers->get($display_name)->setOption('entity_reference_options', $entity_reference_options);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getReferenceableEntities($match = null, $match_operator = 'CONTAINS', $limit = 0)
    {
        if ($display_execution_results = $this->getDisplayExecutionResults($match, $match_operator, $limit)) {
            return $this->stripAdminAndAnchorTagsFromResults($display_execution_results);
        }
        return [];
    }

    /**
     * Fetches the results of executing the display.
     *
     * @param string|null $match
     *   (Optional) Text to match the label against. Defaults to NULL.
     * @param string $match_operator
     *   (Optional) The operation the matching should be done with. Defaults
     *   to "CONTAINS".
     * @param int $limit
     *   Limit the query to a given number of items. Defaults to 0, which
     *   indicates no limiting.
     * @param array|null $ids
     *   Array of entity IDs. Defaults to NULL.
     *
     * @return array
     *   The results.
     */
    protected function getDisplayExecutionResults(?string $match = null, string $match_operator = 'CONTAINS', int $limit = 0, ?array $ids = null)
    {
        $display_name = $this->getConfiguration()['view']['display_name'];
        $arguments = $this->getConfiguration()['view']['arguments'];
        if ($this->initializeView($match, $match_operator, $limit, $ids)) {
            return $this->view->executeDisplay($display_name, $arguments);
        }
        return [];
    }

    /**
     * Strips all admin and anchor tags from a result list.
     *
     * These results are usually displayed in an autocomplete field, which is
     * surrounded by anchor tags. Most tags are allowed inside anchor tags, except
     * for other anchor tags.
     *
     * @param array $results
     *   The result list.
     *
     * @return array
     *   The provided result list with anchor tags removed.
     */
    protected function stripAdminAndAnchorTagsFromResults(array $results): array
    {
        $allowed_tags = Xss::getAdminTagList();
        if (($key = array_search('a', $allowed_tags)) !== false) {
            unset($allowed_tags[$key]);
        }

        $stripped_results = [];
        foreach (Element::children($results) as $id) {
            $entity = $results[$id]['#row']->_entity;
            $stripped_results[$entity->bundle()][$id] = ViewsRenderPipelineMarkup::create(
                Xss::filter($this->renderer->renderInIsolation($results[$id]), $allowed_tags)
            );
        }

        return $stripped_results;
    }

    /**
     * {@inheritdoc}
     */
    public function countReferenceableEntities($match = null, $match_operator = 'CONTAINS')
    {
        $this->getReferenceableEntities($match, $match_operator);
        return $this->view->pager->getTotalItems();
    }

    /**
     * {@inheritdoc}
     * @return int[]|string[]
     */
    public function validateReferenceableEntities(array $ids): array
    {
        $entities = $this->getDisplayExecutionResults(null, 'CONTAINS', 0, $ids);
        if ($entities) {
            return array_keys($entities);
        }
        return [];
    }

    /**
     * Element validate; Check View is valid.
     */
    public static function settingsFormValidate(array $element, FormStateInterface $form_state, $form): void
    {
        // Split view name and display name from the 'view_and_display' value.
        if (!empty($element['view_and_display']['#value'])) {
            [$view, $display] = explode(':', (string) $element['view_and_display']['#value']);
        } else {
            $form_state->setError($element, new TranslatableMarkup('The views entity selection mode requires a view.'));
            return;
        }

        // Explode the 'arguments' string into an actual array. Beware, explode()
        // turns an empty string into an array with one empty string. We'll need an
        // empty array instead.
        $arguments_string = trim((string) $element['arguments']['#value']);
        if ($arguments_string === '') {
            $arguments = [];
        } else {
            // array_map() is called to trim whitespaces from the arguments.
            $arguments = array_map(trim(...), explode(',', $arguments_string));
        }

        $value = [
          'view_name' => $view,
          'display_name' => $display,
          'arguments' => $arguments,
        ];
        $form_state->setValueForElement($element, $value);
    }

}
