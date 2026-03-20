<?php

declare(strict_types=1);

namespace Drupal\node\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a node revision.
 *
 * @internal
 */
class NodeRevisionRevertForm extends ConfirmFormBase
{
    /**
     * The node revision.
     *
     * @var \Drupal\node\NodeInterface
     */
    protected $revision;

    /**
     * Constructs a new NodeRevisionRevertForm.
     *
     * @param \Drupal\Core\Entity\EntityStorageInterface $nodeStorage
     *   The node storage.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
     *   The date formatter service.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     */
    public function __construct(
        /**
         * The node storage.
         */
        protected \Drupal\Core\Entity\EntityStorageInterface $nodeStorage,
        protected \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter,
        protected \Drupal\Component\Datetime\TimeInterface $time
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager')->getStorage('node'),
            $container->get('date.formatter'),
            $container->get('datetime.time')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'node_revision_revert_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('entity.node.version_history', ['node' => $this->revision->id()]);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Revert');
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
    public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node_revision = null)
    {
        $this->revision = $node_revision;

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        // The revision timestamp will be updated when the revision is saved. Keep
        // the original one for the confirmation message.
        $original_revision_timestamp = $this->revision->getRevisionCreationTime();

        $this->revision = $this->prepareRevertedRevision($this->revision, $form_state);
        $this->revision->revision_log = $this->t('Copy of the revision from %date.', ['%date' => $this->dateFormatter->format($original_revision_timestamp)]);
        $this->revision->setRevisionUserId($this->currentUser()->id());
        $this->revision->setRevisionCreationTime($this->time->getRequestTime());
        $this->revision->setChangedTime($this->time->getRequestTime());
        $this->revision->save();

        $this->logger('content')
          ->info('@type: reverted %title revision %revision.', [
            '@type' => $this->revision->bundle(),
            '%title' => $this->revision->label(),
            '%revision' => $this->revision->getRevisionId(),
          ]);
        $this->messenger()
          ->addStatus($this->t('@type %title has been reverted to the revision from %revision-date.', [
            '@type' => $this->revision->getBundleEntity()->label(),
            '%title' => $this->revision->label(),
            '%revision-date' => $this->dateFormatter->format($original_revision_timestamp),
          ]));
        $form_state->setRedirect(
            'entity.node.version_history',
            ['node' => $this->revision->id()]
        );
    }

    /**
     * Prepares a revision to be reverted.
     *
     * @param \Drupal\node\NodeInterface $revision
     *   The revision to be reverted.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return \Drupal\node\NodeInterface
     *   The prepared revision ready to be stored.
     */
    protected function prepareRevertedRevision(NodeInterface $revision, FormStateInterface $form_state)
    {
        return $this->nodeStorage->createRevision($revision);
    }

}
