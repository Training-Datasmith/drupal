<?php

declare(strict_types=1);

namespace Drupal\block\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Url;

/**
 * Provides a deletion confirmation form for the block instance deletion form.
 *
 * @internal
 */
class BlockDeleteForm extends EntityDeleteForm
{
    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('block.admin_display');
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
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        $entity = $this->getEntity();
        $regions = $this->systemRegionList($entity->getTheme(), REGIONS_VISIBLE);
        return $this->t('Are you sure you want to remove the @entity-type %label from the %region region?', [
          '@entity-type' => $entity->getEntityType()->getSingularLabel(),
          '%label' => $entity->label(),
          '%region' => $regions[$entity->getRegion()],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('This will remove the block placement. You will need to <a href=":url">place it again</a> in order to undo this action.', [
          ':url' => Url::fromRoute('block.admin_display_theme', ['theme' => $this->getEntity()->getTheme()])->toString(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDeletionMessage(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        $entity = $this->getEntity();
        $regions = $this->systemRegionList($entity->getTheme(), REGIONS_VISIBLE);
        return $this->t('The @entity-type %label has been removed from the %region region.', [
          '@entity-type' => $entity->getEntityType()->getSingularLabel(),
          '%label' => $entity->label(),
          '%region' => $regions[$entity->getRegion()],
        ]);
    }

    /**
     * Wraps system_region_list().
     */
    protected function systemRegionList($theme, $show = REGIONS_ALL)
    {
        return system_region_list($theme, $show);
    }

}
