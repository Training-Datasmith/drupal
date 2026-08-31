<?php

declare(strict_types=1);

namespace Drupal\system\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Current Theme' condition.
 */
#[Condition(
    id: 'current_theme',
    label: new TranslatableMarkup('Current Theme'),
)]
class CurrentThemeCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface
{
    /**
     * Constructs a CurrentThemeCondition condition plugin.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
     *   The theme manager.
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
     *   The theme handler.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Theme\ThemeManagerInterface $themeManager, protected \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration(): array
    {
        return ['theme' => ''] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['theme'] = [
          '#type' => 'select',
          '#title' => $this->t('Theme'),
          '#default_value' => $this->configuration['theme'],
          '#options' => array_map(fn (\Drupal\Core\Extension\Extension $theme_info) => $theme_info->info['name'], $this->themeHandler->listInfo()),
        ];
        return parent::buildConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        $this->configuration['theme'] = $form_state->getValue('theme');
        parent::submitConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate()
    {
        if (!$this->configuration['theme']) {
            return true;
        }

        return $this->themeManager->getActiveTheme()->getName() == $this->configuration['theme'];
    }

    /**
     * {@inheritdoc}
     */
    public function summary(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        if ($this->isNegated()) {
            return $this->t('The current theme is not @theme', ['@theme' => $this->configuration['theme']]);
        }

        return $this->t('The current theme is @theme', ['@theme' => $this->configuration['theme']]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts(): array
    {
        $contexts = parent::getCacheContexts();
        $contexts[] = 'theme';
        return $contexts;
    }

}
