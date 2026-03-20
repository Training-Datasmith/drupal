<?php

declare(strict_types=1);

namespace Drupal\language\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\language\Entity\ContentLanguageSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the content language settings for this site.
 *
 * @internal
 */
class ContentLanguageSettingsForm extends FormBase
{
    /**
     * If this validator can handle multiple arguments.
     *
     * @var bool
     */
    protected $multipleCapable = true;

    /**
     * Constructs an \Drupal\views\Plugin\views\argument_validator\Entity object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
     *   The entity type bundle info.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
     *   The module handler.
     */
    public function __construct(protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager, protected \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo, protected \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('entity_type.bundle.info'),
            $container->get('module_handler')
        );
    }

    /**
     * The _title_callback for the language.content_settings_page route.
     *
     * @return \Drupal\Core\StringTranslation\TranslatableMarkup
     *   The page title.
     */
    public function getTitle(): string
    {
        if ($this->moduleHandler->moduleExists('content_translation')) {
            return $this->t('Content language and translation');
        }
        return $this->t('Content language');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'language_content_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $entity_types = $this->entityTypeManager->getDefinitions();
        $labels = [];
        $default = [];

        $bundles = $this->entityTypeBundleInfo->getAllBundleInfo();
        $language_configuration = [];
        foreach ($entity_types as $entity_type_id => $entity_type) {
            if (!$entity_type instanceof ContentEntityTypeInterface) {
                continue;
            }
            if (!$entity_type->hasKey('langcode')) {
                continue;
            }
            if (!isset($bundles[$entity_type_id])) {
                continue;
            }
            $labels[$entity_type_id] = $entity_type->getLabel() ?: $entity_type_id;
            $default[$entity_type_id] = false;

            // Check whether we have any custom setting.
            foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
                $config = ContentLanguageSettings::loadByEntityTypeBundle($entity_type_id, $bundle);
                if (!$config->isDefaultConfiguration()) {
                    $default[$entity_type_id] = $entity_type_id;
                }
                $language_configuration[$entity_type_id][$bundle] = $config;
            }
        }

        asort($labels);

        $form = [
          '#labels' => $labels,
          '#attached' => [
            'library' => [
              'language/drupal.language.admin',
            ],
          ],
          '#attributes' => [
            'class' => 'language-content-settings-form',
          ],
        ];

        $form['entity_types'] = [
          '#title' => $this->t('Custom language settings'),
          '#type' => 'checkboxes',
          '#options' => $labels,
          '#default_value' => $default,
        ];

        $form['settings'] = ['#tree' => true];

        foreach ($labels as $entity_type_id => $label) {
            $entity_type = $entity_types[$entity_type_id];

            $form['settings'][$entity_type_id] = [
              '#title' => $label,
              '#type' => 'details',
              '#entity_type' => $entity_type_id,
              '#theme' => 'language_content_settings_table',
              '#bundle_label' => $entity_type->getBundleLabel() ?: $label,
              '#states' => [
                'visible' => [
                  ':input[name="entity_types[' . $entity_type_id . ']"]' => ['checked' => true],
                ],
              ],
            ];

            foreach ($bundles[$entity_type_id] as $bundle => $bundle_info) {
                $form['settings'][$entity_type_id][$bundle]['settings'] = [
                  '#type' => 'item',
                  '#label' => $bundle_info['label'],
                  'language' => [
                    '#type' => 'language_configuration',
                    '#entity_information' => [
                      'entity_type' => $entity_type_id,
                      'bundle' => $bundle,
                    ],
                    '#default_value' => $language_configuration[$entity_type_id][$bundle],
                  ],
                ];
            }
        }

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save configuration'),
          '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $entity_types = $form_state->getValue('entity_types');
        foreach ($form_state->getValue('settings') as $entity_type => $entity_settings) {
            foreach ($entity_settings as $bundle => $bundle_settings) {
                $config = ContentLanguageSettings::loadByEntityTypeBundle($entity_type, $bundle);
                if (empty($entity_types[$entity_type])) {
                    $bundle_settings['settings']['language']['language_alterable'] = false;
                }
                $config->setDefaultLangcode($bundle_settings['settings']['language']['langcode'])
                  ->setLanguageAlterable($bundle_settings['settings']['language']['language_alterable'])
                  ->save();
            }
        }
        $this->messenger()->addStatus($this->t('Settings successfully updated.'));
    }

}
