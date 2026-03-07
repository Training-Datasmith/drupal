<?php

declare(strict_types=1);

namespace Drupal\Component\Gettext;

/**
 * PoItem handles one translation.
 *
 * @todo This class contains some really old legacy code.
 * @see https://www.drupal.org/node/1637662
 */
class PoItem implements \Stringable
{
    /**
     * The delimiter used to split plural strings.
     *
     * This is the ETX (End of text) character and is used as a minimal means to
     * separate singular and plural variants in source and translation text. It
     * was found to be the most compatible delimiter for the supported databases.
     */
    public const DELIMITER = "\03";

    /**
     * The language code this translation is in.
     *
     * @var string
     */
    protected $langcode;

    /**
     * The context this translation belongs to.
     *
     * @var string
     */
    protected $context = '';

    /**
     * The source string or array of strings if it has plurals.
     *
     * @var string|array
     *
     * @see $plural
     */
    protected $source;

    /**
     * Flag indicating if this translation has plurals.
     *
     * @var bool
     */
    protected $plural;

    /**
     * The comment of this translation.
     *
     * @var string
     */
    protected $comment;

    /**
     * The translation string or array of strings if it has plurals.
     *
     * @var string|array
     * @see $plural
     */
    protected $translation;

    /**
     * Gets the language code of the currently used language.
     *
     * @return string
     *   The language code for this item.
     */
    public function getLangcode()
    {
        return $this->langcode;
    }

    /**
     * Set the language code of the current language.
     *
     * @param string $langcode
     *   The language code of the current language.
     */
    public function setLangcode($langcode): void
    {
        $this->langcode = $langcode;
    }

    /**
     * Gets the context this translation belongs to.
     *
     * @return string
     *   The context for this translation.
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set the context this translation belongs to.
     *
     * @param string $context
     *   The context this translation belongs to.
     */
    public function setContext($context): void
    {
        $this->context = $context;
    }

    /**
     * Gets the source string(s) if the translation has plurals.
     *
     * @return string|array
     *   The source string or array of strings if it has plurals.
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Sets the source string(s) if the translation has plurals.
     *
     * @param string|array $source
     *   The source string or the array of strings if the translation has plurals.
     */
    public function setSource($source): void
    {
        $this->source = $source;
    }

    /**
     * Gets the translation string(s) if the translation has plurals.
     *
     * @return string|array
     *   The translation string or array of strings if it has plurals.
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * Sets the translation string(s) if the translation has plurals.
     *
     * @param string|array $translation
     *   The translation string or the array of strings if the translation has
     *   plurals.
     */
    public function setTranslation($translation): void
    {
        $this->translation = $translation;
    }

    /**
     * Set if the translation has plural values.
     *
     * @param bool $plural
     *   TRUE, if the translation has plural values. FALSE otherwise.
     */
    public function setPlural($plural): void
    {
        $this->plural = $plural;
    }

    /**
     * Get if the translation has plural values.
     *
     * @return bool
     *   TRUE if the translation has plurals, otherwise FALSE.
     */
    public function isPlural()
    {
        return $this->plural;
    }

    /**
     * Gets the comment of this translation.
     *
     * @return string
     *   The comment of this translation.
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set the comment of this translation.
     *
     * @param string $comment
     *   The comment of this translation.
     */
    public function setComment($comment): void
    {
        $this->comment = $comment;
    }

    /**
     * Create the PoItem from a structured array.
     *
     * @param array $values
     *   A structured array to create the PoItem from.
     */
    public function setFromArray(array $values = []): void
    {
        if (isset($values['context'])) {
            $this->setContext($values['context']);
        }
        if (isset($values['source'])) {
            $this->setSource($values['source']);
        }
        if (isset($values['translation'])) {
            $this->setTranslation($values['translation']);
        }
        if (isset($values['comment'])) {
            $this->setComment($values['comment']);
        }
        if (isset($this->source) && str_contains($this->source, self::DELIMITER)) {
            $this->setSource(explode(self::DELIMITER, $this->source));
            $this->setTranslation(explode(self::DELIMITER, $this->translation ?? ''));
            $this->setPlural(count($this->source) > 1);
        }
    }

    /**
     * Output the PoItem as a string.
     */
    public function __toString(): string
    {
        return (string) $this->formatItem();
    }

    /**
     * Format the POItem as a string.
     */
    private function formatItem(): string
    {
        $output = '';

        // Format string context.
        if (!empty($this->context)) {
            $output .= 'msgctxt ' . $this->formatString($this->context);
        }

        // Format translation.
        if ($this->plural) {
            $output .= $this->formatPlural();
        } else {
            $output .= $this->formatSingular();
        }

        // Add one empty line to separate the translations.
        $output .= "\n";

        return $output;
    }

    /**
     * Formats a plural translation.
     */
    private function formatPlural(): string
    {
        $output = '';

        // Format source strings.
        $output .= 'msgid ' . $this->formatString($this->source[0]);
        $output .= 'msgid_plural ' . $this->formatString($this->source[1]);

        foreach ($this->translation as $i => $trans) {
            if (isset($this->translation[$i])) {
                $output .= 'msgstr[' . $i . '] ' . $this->formatString($trans);
            } else {
                $output .= 'msgstr[' . $i . '] ""' . "\n";
            }
        }

        return $output;
    }

    /**
     * Formats a singular translation.
     */
    private function formatSingular(): string
    {
        $output = '';
        $output .= 'msgid ' . $this->formatString($this->source);
        return $output . ('msgstr ' . isset($this->translation) ? $this->formatString($this->translation) : '""' . "\n");
    }

    /**
     * Formats a string for output on multiple lines.
     */
    private function formatString($string): string
    {
        // Escape characters for processing.
        $string = addcslashes((string) $string, "\0..\37\\\"");

        // Always include a line break after the explicit \n line breaks from
        // the source string. Otherwise wrap at 70 chars to accommodate the extra
        // format overhead too.
        $parts = explode("\n", wordwrap(str_replace('\n', "\\n\n", $string), 70, " \n"));

        // Multiline string should be exported starting with a "" and newline to
        // have all lines aligned on the same column.
        if (count($parts) > 1) {
            return "\"\"\n\"" . implode("\"\n\"", $parts) . "\"\n";
        }
        return "\"$parts[0]\"\n";
    }

}
