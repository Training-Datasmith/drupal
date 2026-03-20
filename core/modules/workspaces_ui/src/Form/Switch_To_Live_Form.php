<?php

declare(strict_types=1);

namespace Drupal\workspaces_ui\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\WorkspaceSafeFormInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form that switches to the live version of the site.
 */
class SwitchToLiveForm extends ConfirmFormBase implements ContainerInjectionInterface, WorkspaceSafeFormInterface
{
    /**
     * Constructs a new SwitchToLiveForm.
     *
     * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
     *   The workspace manager.
     */
    public function __construct(protected \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('workspaces.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'switch_to_live_form';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Would you like to switch to the live version of the site?');
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Switch to the live version of the site.');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('<current>');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->workspaceManager->switchToLive();
        $this->messenger()->addMessage($this->t('You are now viewing the live version of the site.'));
    }

}
