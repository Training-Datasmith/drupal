<?php

declare(strict_types=1);

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle HTML responses.
 */
class HtmlResponseSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs a HtmlResponseSubscriber object.
     *
     * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $htmlResponseAttachmentsProcessor
     *   The HTML response attachments processor service.
     */
    public function __construct(protected \Drupal\Core\Render\AttachmentsResponseProcessorInterface $htmlResponseAttachmentsProcessor)
    {
    }

    /**
     * Processes attachments for HtmlResponse responses.
     *
     * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
     *   The event to process.
     */
    public function onRespond(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response instanceof HtmlResponse) {
            return;
        }

        $event->setResponse($this->htmlResponseAttachmentsProcessor->processAttachments($response));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        $events[KernelEvents::RESPONSE][] = ['onRespond'];
        return $events;
    }

}
