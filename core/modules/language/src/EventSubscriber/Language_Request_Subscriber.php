<?php

declare(strict_types=1);

namespace Drupal\language\EventSubscriber;

use Drupal\Core\DrupalKernelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the $request property on the language manager.
 */
class LanguageRequestSubscriber implements EventSubscriberInterface
{
    /**
     * Constructs a LanguageRequestSubscriber object.
     *
     * @param \Drupal\language\ConfigurableLanguageManagerInterface $languageManager
     *   The language manager service.
     * @param \Drupal\language\LanguageNegotiatorInterface $negotiator
     *   The language negotiator.
     * @param \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translation
     *   The translation service.
     * @param \Drupal\Core\Session\AccountInterface $currentUser
     *   The current active user.
     */
    public function __construct(protected \Drupal\language\ConfigurableLanguageManagerInterface $languageManager, protected \Drupal\language\LanguageNegotiatorInterface $negotiator, protected \Drupal\Core\StringTranslation\Translator\TranslatorInterface $translation, protected \Drupal\Core\Session\AccountInterface $currentUser)
    {
    }

    /**
     * Initializes the language manager at the beginning of the request.
     *
     * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
     *   The Event to process.
     */
    public function onKernelRequestLanguage(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->setLanguageOverrides();
        }
    }

    /**
     * Initializes config overrides whenever the service container is rebuilt.
     */
    public function onContainerInitializeSubrequestFinished(): void
    {
        $this->setLanguageOverrides();
    }

    /**
     * Sets the language for config overrides on the language manager.
     */
    private function setLanguageOverrides(): void
    {
        $this->negotiator->setCurrentUser($this->currentUser);
        $this->languageManager->setNegotiator($this->negotiator);
        $this->languageManager->setConfigOverrideLanguage($this->languageManager->getCurrentLanguage());
        // After the language manager has initialized, set the default langcode for
        // the string translations.
        $langcode = $this->languageManager->getCurrentLanguage()->getId();
        $this->translation->setDefaultLangcode($langcode);
    }

    /**
     * Registers the methods in this class that should be listeners.
     *
     * @return array
     *   An array of event listener definitions.
     */
    public static function getSubscribedEvents(): array
    {
        $events[KernelEvents::REQUEST][] = ['onKernelRequestLanguage', 255];
        $events[DrupalKernelInterface::CONTAINER_INITIALIZE_SUBREQUEST_FINISHED][] = ['onContainerInitializeSubrequestFinished', 255];

        return $events;
    }

}
