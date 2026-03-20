<?php

declare(strict_types=1);

namespace Drupal\image\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an add form for image effects.
 *
 * @internal
 */
class ImageEffectAddForm extends ImageEffectFormBase
{
    /**
     * Constructs a new ImageEffectAddForm.
     *
     * @param \Drupal\image\ImageEffectManager $effectManager
     *   The image effect manager.
     */
    public function __construct(protected \Drupal\image\ImageEffectManager $effectManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('plugin.manager.image.effect')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, ?ImageStyleInterface $image_style = null, $image_effect = null)
    {
        $form = parent::buildForm($form, $form_state, $image_style, $image_effect);

        $form['#title'] = $this->t('Add %label effect to style %style', [
          '%label' => $this->imageEffect->label(),
          '%style' => $image_style->label(),
        ]);
        $form['actions']['submit']['#value'] = $this->t('Add effect');

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareImageEffect($image_effect)
    {
        $image_effect = $this->effectManager->createInstance($image_effect);
        // Set the initial weight so this effect comes last.
        $image_effect->setWeight(count($this->imageStyle->getEffects()));
        return $image_effect;
    }

}
