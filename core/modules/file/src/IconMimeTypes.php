<?php

declare(strict_types=1);

namespace Drupal\file;

/**
 * A utility class for working with MIME types.
 */
final class IconMimeTypes {

  /**
   * Gets a class for the icon for a MIME type.
   *
   * @param string $mimeType
   *   A MIME type.
   *
   * @return string
   *   A class associated with the file.
   */
  public static function getIconClass(string $mimeType): string {
    // Search for a group with the files MIME type.
    $genericMime = (string) self::getGenericMimeType($mimeType);
    if (!empty($genericMime)) {
      return $genericMime;
    }

    // Use generic icons for each category that provides such icons.
    foreach (['audio', 'image', 'text', 'video'] as $category) {
      if (str_starts_with($mimeType, $category)) {
        return $category;
      }
    }

    // If there's no generic icon for the type the general class.
    return 'general';
  }

  /**
   * Determines the generic icon MIME package based on a file's MIME type.
   *
   * @param string $mimeType
   *   A MIME type.
   *
   * @return string|false
   *   The generic icon MIME package expected for this file.
   */
  public static function getGenericMimeType(string $mimeType): string | false {
    // cspell:disable
    return match ($mimeType) {
        'application/msword', 'application/vnd.ms-word.document.macroEnabled.12', 'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.text-template', 'application/vnd.oasis.opendocument.text-master', 'application/vnd.oasis.opendocument.text-web', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.stardivision.writer', 'application/vnd.sun.xml.writer', 'application/vnd.sun.xml.writer.template', 'application/vnd.sun.xml.writer.global', 'application/vnd.wordperfect', 'application/x-abiword', 'application/x-applix-word', 'application/x-kword', 'application/x-kword-crypt' => 'x-office-document',
        'application/vnd.ms-excel', 'application/vnd.ms-excel.sheet.macroEnabled.12', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.spreadsheet-template', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.stardivision.calc', 'application/vnd.sun.xml.calc', 'application/vnd.sun.xml.calc.template', 'application/vnd.lotus-1-2-3', 'application/x-applix-spreadsheet', 'application/x-gnumeric', 'application/x-kspread', 'application/x-kspread-crypt' => 'x-office-spreadsheet',
        'application/vnd.ms-powerpoint', 'application/vnd.ms-powerpoint.presentation.macroEnabled.12', 'application/vnd.oasis.opendocument.presentation', 'application/vnd.oasis.opendocument.presentation-template', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.stardivision.impress', 'application/vnd.sun.xml.impress', 'application/vnd.sun.xml.impress.template', 'application/x-kpresenter' => 'x-office-presentation',
        'application/zip', 'application/x-zip', 'application/stuffit', 'application/x-stuffit', 'application/x-7z-compressed', 'application/x-ace', 'application/x-arj', 'application/x-bzip', 'application/x-bzip-compressed-tar', 'application/x-compress', 'application/x-compressed-tar', 'application/x-cpio-compressed', 'application/x-deb', 'application/x-gzip', 'application/x-java-archive', 'application/x-lha', 'application/x-lhz', 'application/x-lzop', 'application/x-rar', 'application/x-rpm', 'application/x-tzo', 'application/x-tar', 'application/x-tarz', 'application/x-tgz' => 'package-x-generic',
        'application/ecmascript', 'application/javascript', 'application/mathematica', 'application/vnd.mozilla.xul+xml', 'application/x-asp', 'application/x-awk', 'application/x-cgi', 'application/x-csh', 'application/x-m4', 'application/x-perl', 'application/x-php', 'application/x-ruby', 'application/x-shellscript', 'text/javascript', 'text/vnd.wap.wmlscript', 'text/x-emacs-lisp', 'text/x-haskell', 'text/x-literate-haskell', 'text/x-lua', 'text/x-makefile', 'text/x-matlab', 'text/x-python', 'text/x-sql', 'text/x-tcl' => 'text-x-script',
        'application/xhtml+xml' => 'text-html',
        'application/x-macbinary', 'application/x-ms-dos-executable', 'application/x-pef-executable' => 'application-x-executable',
        'application/pdf', 'application/x-pdf', 'applications/vnd.pdf', 'text/pdf', 'text/x-pdf' => 'application-pdf',
        default => FALSE,
    };
    // cspell:enable
  }

}
