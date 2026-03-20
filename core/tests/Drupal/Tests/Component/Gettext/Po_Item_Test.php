<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Gettext;

use Drupal\Component\Gettext\PoItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Gettext\PoItem.
 */
#[CoversClass(PoItem::class)]
#[Group('Gettext')]
class PoItemTest extends TestCase
{
    /**
     * @return array
     *   - Source string
     *   - Context (optional)
     *   - Translated string (optional)
     *   - Expected value
     */
    public static function providerStrings(): array
    {
        // cSpell:disable
        return [
          [
            '',
            null,
            null,
            'msgid ""' . "\n" . 'msgstr ""' . "\n\n",
          ],
          // Translated String without contesxt.
          [
            'Next',
            null,
            'Suivant',
            'msgid "Next"' . "\n" . 'msgstr "Suivant"' . "\n\n",
          ],
          // Translated string with context.
          [
            'Apr',
            'Abbreviated month name',
            'Avr',
            'msgctxt "Abbreviated month name"' . "\n" . 'msgid "Apr"' . "\n" . 'msgstr "Avr"' . "\n\n",
          ],
          // Translated string with placeholder.
          [
            '%email is not a valid email address.',
            null,
            '%email n\'est pas une adresse de courriel valide.',
            'msgid "%email is not a valid email address."' . "\n" . 'msgstr "%email n\'est pas une adresse de courriel valide."' . "\n\n",
          ],
          // Translated Plural String without context.
          [
            ['Installed theme', 'Installed themes'],
            null,
            ['Thème installé', 'Thèmes installés'],
            'msgid "Installed theme"' . "\n" . 'msgid_plural "Installed themes"' . "\n" . 'msgstr[0] "Thème installé"' . "\n" . 'msgstr[1] "Thèmes installés"' . "\n\n",
          ],
        ];
        // cSpell:enable
    }

    #[DataProvider('providerStrings')]
    public function testFormat($source, $context, $translation, $expected): void
    {
        $item = new PoItem();

        $item->setSource($source);

        if (is_array($source)) {
            $item->setPlural(true);
        }
        if (!empty($context)) {
            $item->setContext($context);
        }
        if (!empty($translation)) {
            $item->setTranslation($translation);
        }

        $this->assertEquals($expected, (string) $item);
    }

}
