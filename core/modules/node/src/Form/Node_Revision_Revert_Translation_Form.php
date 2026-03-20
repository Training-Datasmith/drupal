<?php

declare(strict_types=1);

namespace Drupal\node\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a node revision for a single translation.
 *
 * @internal
 */
class NodeRevisionRevertTranslationForm extends NodeRevisionRevertForm
{
    /**
     * The language to be reverted.
     *
     * @var string
     */
    protected $langcode;

    /**
     * Constructs a new NodeRevisionRevertTranslationForm.
     *
     * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
     *   The node storage.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
     *   The date formatter service.
     * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
     *   The language manager.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(EntityStorageInterface $node_storage, DateFormatterInterface $date_formatter, protected \Drupal\Core\Language\LanguageManagerInterface $languageManager, TimeInterface $time)
    {
        parent::__construct($node_storage, $date_formatter, $time);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager')->getStorage('node'),
            $container->get('date.formatter'),
            $container->get('language_manager'),
            $container->get('datetime.time')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'node_revision_revert_translation_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to revert @language translation to the revision from %revision-date?', [
          '@language' => $this->languageManager->getLanguageName($this->langcode),
          '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, ?\Drupal\node\NodeInterface $node_revision = null, $langcode = null)
    {
        $this->langcode = $langcode;
        $form = parent::buildForm($form, $form_state, $node_revision);

        // Unless untranslatable fields are configured to affect only the default
        // translation, we need to ask the user whether they should be included in
        // the revert process.
        $default_translation_affected = $this->revision->isDefaultTranslationAffectedOnly();
        $form['revert_untranslated_fields'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Revert content shared among translations'),
          '#default_value' => $default_translation_affected && $this->revision->getTranslation($this->langcode)->isDefaultTranslation(),
          '#access' => !$default_translation_affected,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareRevertedRevision(NodeInterface $revision, FormStateInterface $form_state)
    {
        $revert_untranslated_fields = (bool) $form_state->getValue('revert_untranslated_fields');
        $translation = $revision->getTranslation($this->langcode);
        return $this->nodeStorage->createRevision($translation, true, $revert_untranslated_fields);
    }

}
