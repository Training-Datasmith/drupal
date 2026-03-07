<?php

namespace Drupal\file\EventSubscriber;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sanitizes uploaded filenames.
 *
 * @package Drupal\file\EventSubscriber
 */
class FileEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new file event listener.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'transliteration')]
    protected TransliterationInterface $transliteration,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FileUploadSanitizeNameEvent::class => 'sanitizeFilename',
    ];
  }

  /**
   * Sanitizes the filename of a file being uploaded.
   *
   * @param \Drupal\Core\File\Event\FileUploadSanitizeNameEvent $event
   *   File upload sanitize name event.
   *
   * @see file_form_system_file_system_settings_alter()
   */
  public function sanitizeFilename(FileUploadSanitizeNameEvent $event): void {
    $fileSettings = $this->configFactory->get('file.settings');
    $transliterate = $fileSettings->get('filename_sanitization.transliterate');

    $filename = $event->getFilename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension !== '') {
      $extension = '.' . $extension;
      $filename = pathinfo($filename, PATHINFO_FILENAME);
    }

    // Sanitize the filename according to configuration.
    $alphanumeric = $fileSettings->get('filename_sanitization.replace_non_alphanumeric');
    $replacement = $fileSettings->get('filename_sanitization.replacement_character');
    if ($transliterate) {
      $transliterated_filename = $this->transliteration->transliterate(
        $filename,
        $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
        $replacement
      );
      if (mb_strlen($transliterated_filename) > 0) {
        $filename = $transliterated_filename;
      }
      else {
        // If transliteration has resulted in a zero length string enable the
        // 'replace_non_alphanumeric' option and ignore the result of
        // transliteration.
        $alphanumeric = TRUE;
      }
    }
    if ($fileSettings->get('filename_sanitization.replace_whitespace')) {
      $filename = preg_replace('/\s/u', (string) $replacement, trim($filename));
    }
    // Only honor replace_non_alphanumeric if transliterate is enabled.
    if ($transliterate && $alphanumeric) {
      $filename = preg_replace('/[^0-9A-Za-z_.-]/u', (string) $replacement, (string) $filename);
    }
    if ($fileSettings->get('filename_sanitization.deduplicate_separators')) {
      $filename = preg_replace('/(_)_+|(\.)\.+|(-)-+/u', (string) $replacement, (string) $filename);
      // Replace multiple separators with single one.
      $filename = preg_replace('/(_|\.|\-)[(_|\.|\-)]+/u', (string) $replacement, (string) $filename);
      $filename = preg_replace('/' . preg_quote((string) $replacement) . '[' . preg_quote((string) $replacement) . ']*/u', (string) $replacement, (string) $filename);
      // Remove replacement character from the end of the filename.
      $filename = rtrim((string) $filename, $replacement);

      // If there is an extension remove dots from the end of the filename to
      // prevent duplicate dots.
      if (!empty($extension)) {
        $filename = rtrim($filename, '.');
      }
    }
    if ($fileSettings->get('filename_sanitization.lowercase')) {
      // Force lowercase to prevent issues on case-insensitive file systems.
      $filename = mb_strtolower((string) $filename);
    }
    $event->setFilename($filename . $extension);
  }

}
