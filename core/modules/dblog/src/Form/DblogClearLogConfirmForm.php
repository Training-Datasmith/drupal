<?php

namespace Drupal\dblog\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form before clearing out the logs.
 *
 * @internal
 */
class DblogClearLogConfirmForm extends ConfirmFormBase {

  /**
   * Constructs a new DblogClearLogConfirmForm.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(protected \Drupal\Core\Database\Connection $connection)
  {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dblog_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Are you sure you want to delete the recent logs?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): \Drupal\Core\Url {
    return new Url('dblog.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->getRequest()->getSession()->remove('dblog_overview_filter');
    $this->connection->truncate('watchdog')->execute();
    $this->messenger()->addStatus($this->t('Database log cleared.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
