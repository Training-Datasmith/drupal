<?php

declare(strict_types=1);

namespace Drupal\options\Plugin\views\argument;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\argument\StringArgument;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Argument handler for list field to show the human readable name in summary.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
    id: 'string_list_field',
)]
class StringListField extends StringArgument
{
    use FieldAPIHandlerTrait;

    /**
     * Stores the allowed values of this field.
     *
     * @var array
     */
    protected $allowedValues;

    /**
     * {@inheritdoc}
     */
    public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = null): void
    {
        parent::init($view, $display, $options);

        $field_storage = $this->getFieldStorageDefinition();
        $this->allowedValues = options_allowed_values($field_storage);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineOptions()
    {
        $options = parent::defineOptions();

        $options['summary']['contains']['human'] = ['default' => false];

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state): void
    {
        parent::buildOptionsForm($form, $form_state);

        $form['summary']['human'] = [
          '#title' => $this->t('Display list value as human readable'),
          '#type' => 'checkbox',
          '#default_value' => $this->options['summary']['human'],
          '#states' => [
            'visible' => [
              ':input[name="options[default_action]"]' => ['value' => 'summary'],
            ],
          ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summaryName($data): string|\Drupal\Core\Field\FieldFilteredMarkup
    {
        $value = $data->{$this->name_alias};
        // If the list element has a human readable name show it.
        if (isset($this->allowedValues[$value]) && !empty($this->options['summary']['human'])) {
            $value = $this->allowedValues[$value];
        }
        return FieldFilteredMarkup::create($this->caseTransform($value, $this->options['case']));
    }

}
