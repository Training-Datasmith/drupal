<?php

declare(strict_types=1);

namespace Drupal\layout_builder\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to confirm the removal of a block.
 *
 * @internal
 *   Form classes are internal.
 */
class RemoveBlockForm extends LayoutRebuildConfirmFormBase
{
    /**
     * The current region.
     *
     * @var string
     */
    protected $region;

    /**
     * The UUID of the block being removed.
     *
     * @var string
     */
    protected $uuid;

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        $label = $this->sectionStorage
          ->getSection($this->delta)
          ->getComponent($this->uuid)
          ->getPlugin()
          ->label();

        return $this->t('Are you sure you want to remove the %label block?', ['%label' => $label]);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Remove');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'layout_builder_remove_block';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = null, $delta = null, $region = null, $uuid = null)
    {
        $this->region = $region;
        $this->uuid = $uuid;
        return parent::buildForm($form, $form_state, $section_storage, $delta);
    }

    /**
     * {@inheritdoc}
     */
    protected function handleSectionStorage(SectionStorageInterface $section_storage, FormStateInterface $form_state)
    {
        $section_storage->getSection($this->delta)->removeComponent($this->uuid);
    }

}
