<?php

declare(strict_types=1);

namespace Drupal\system\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityDeleteForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a form to delete a date format.
 *
 * @internal
 */
class DateFormatDeleteForm extends EntityDeleteForm
{
    /**
     * Constructs a DateFormatDeleteForm object.
     */
    public function __construct(
        protected DateFormatterInterface $dateFormatter,
        protected TimeInterface $time,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('date.formatter'),
            $container->get('datetime.time'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Are you sure you want to delete the format %name : %format?', [
          '%name' => $this->entity->label(),
          '%format' => $this->dateFormatter->format($this->time->getRequestTime(), $this->entity->id()),
        ]);
    }

}
