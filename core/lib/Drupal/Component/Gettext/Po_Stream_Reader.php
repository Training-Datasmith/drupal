<?php

declare (strict_types=1);
namespace Drupal\Component\Gettext;

use Drupal\Component\Render\Formattable_Markup;
/**
 * Implements Gettext PO stream reader.
 *
 * The PO file format parsing is implemented according to the documentation at
 * http://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
 */
class Po_Stream_Reader implements Po_Stream_Interface, Po_Reader_Interface
{
    /**
     * Source line number of the stream being parsed.
     *
     * @var int
     */
    protected $line_number = 0;
    /**
     * Parser context for the stream reader state machine.
     *
     * Possible contexts are:
     *  - 'COMMENT' (#)
     *  - 'MSGID' (msgid)
     *  - 'MSGID_PLURAL' (msgid_plural)
     *  - 'MSGCTXT' (msgctxt)
     *  - 'MSGSTR' (msgstr or msgstr[])
     *  - 'MSGSTR_ARR' (msgstr_arg)
     *
     * @var string
     */
    protected $context = 'COMMENT';
    /**
     * Current entry being read. Incomplete.
     *
     * @var array
     */
    protected $current_item = [];
    /**
     * Current plural index for plural translations.
     *
     * @var int
     */
    protected $current_plural_index = 0;
    /**
     * URI of the PO stream that is being read.
     *
     * @var string
     */
    protected $uri = '';
    /**
     * Language code for the PO stream being read.
     *
     * @var string
     */
    protected $langcode;
    /**
     * File handle of the current PO stream.
     *
     * @var resource
     */
    protected $fd;
    /**
     * The PO stream header.
     *
     * @var \Drupal\Component\Gettext\PoHeader
     */
    protected $header;
    /**
     * Object wrapper for the last read source/translation pair.
     *
     * @var \Drupal\Component\Gettext\PoItem
     */
    protected $last_item;
    /**
     * Indicator of whether the stream reading is finished.
     *
     * @var bool
     */
    protected $finished;
    /**
     * Array of translated error strings recorded on reading this stream so far.
     *
     * @var array
     */
    protected $errors;
    /**
     * {@inheritdoc}
     */
    public function get_langcode()
    {
        return $this->langcode;
    }
    /**
     * {@inheritdoc}
     */
    public function set_langcode($langcode): void
    {
        $this->langcode = $langcode;
    }
    /**
     * {@inheritdoc}
     */
    public function get_header()
    {
        return $this->header;
    }
    /**
     * Implements Drupal\Component\Gettext\PoMetadataInterface::setHeader().
     *
     * Not applicable to stream reading and therefore not implemented.
     */
    public function set_header(Po_Header $header)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_uri()
    {
        return $this->uri;
    }
    /**
     * {@inheritdoc}
     */
    public function set_uri($uri): void
    {
        $this->uri = $uri;
    }
    /**
     * Implements Drupal\Component\Gettext\PoStreamInterface::open().
     *
     * Opens the stream and reads the header. The stream is ready for reading
     * items after.
     *
     * @throws \Exception
     *   If the URI is not yet set.
     */
    public function open(): void
    {
        if (!empty($this->uri)) {
            $this->fd = fopen($this->uri, 'rb');
            $this->read_header();
        } else {
            throw new \Exception('Cannot open stream without URI set.');
        }
    }
    /**
     * Implements Drupal\Component\Gettext\PoStreamInterface::close().
     *
     * @throws \Exception
     *   If the stream is not open.
     */
    public function close(): void
    {
        if ($this->fd) {
            fclose($this->fd);
        } else {
            throw new \Exception('Cannot close stream that is not open.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function read_item()
    {
        // Clear out the last item.
        $this->last_item = null;
        // Read until finished with the stream or a complete item was identified.
        while (!$this->finished && is_null($this->last_item)) {
            $this->read_line();
        }
        return $this->last_item;
    }
    /**
     * Sets the seek position for the current PO stream.
     *
     * @param int $seek
     *   The new seek position to set.
     */
    public function set_seek($seek): void
    {
        fseek($this->fd, $seek);
    }
    /**
     * Gets the pointer position of the current PO stream.
     */
    public function get_seek(): int|false
    {
        return ftell($this->fd);
    }
    /**
     * Read the header from the PO stream.
     *
     * The header is a special case PoItem, using the empty string as source and
     * key-value pairs as translation. We just reuse the item reader logic to
     * read the header.
     */
    private function read_header(): void
    {
        $item = $this->read_item();
        // Handle the case properly when the .po file is empty (0 bytes).
        if (!$item) {
            return;
        }
        $header = new Po_Header();
        $header->set_from_string(trim($item->get_translation()));
        $this->header = $header;
    }
    /**
     * Reads a line from the PO stream and stores data internally.
     *
     * Expands $this->current_item based on new data for the current item. If
     * this line ends the current item, it is saved with setItemFromArray() with
     * data from $this->current_item.
     *
     * An internal state machine is maintained in this reader using
     * $this->context as the reading state. PO items are in between COMMENT
     * states (when items have at least one line or comment in between them) or
     * indicated by MSGSTR or MSGSTR_ARR followed immediately by an MSGID or
     * MSGCTXT (when items closely follow each other).
     *
     * @return bool|null
     *   FALSE if an error was logged, NULL otherwise. The errors are considered
     *   non-blocking, so reading can continue, while the errors are collected
     *   for later presentation.
     */
    private function read_line()
    {
        // Read a line and set the stream finished indicator if it was not
        // possible anymore.
        $line = fgets($this->fd);
        $this->finished = $line === false;
        if (!$this->finished) {
            if ($this->line_number == 0) {
                // The first line might come with a UTF-8 BOM, which should be removed.
                $line = str_replace("﻿", '', $line);
                // Current plurality for 'msgstr[]'.
                $this->current_plural_index = 0;
            }
            // Track the line number for error reporting.
            $this->line_number++;
            // Initialize common values for error logging.
            $log_vars = ['%uri' => $this->get_uri(), '%line' => $this->line_number];
            // Trim away the linefeed. \\n might appear at the end of the string if
            // another line continuing the same string follows. We can remove that.
            $line = trim(strtr($line, ["\\\n" => '']));
            if (!strncmp('#', $line, 1)) {
                // Lines starting with '#' are comments.
                if ($this->context == 'COMMENT') {
                    // Already in comment context, add to current comment.
                    $this->current_item['#'][] = substr($line, 1);
                } elseif ($this->context == 'MSGSTR' || $this->context == 'MSGSTR_ARR') {
                    // We are currently in string context, save current item.
                    $this->set_item_from_array($this->current_item);
                    // Start a new entry for the comment.
                    $this->current_item = [];
                    $this->current_item['#'][] = substr($line, 1);
                    $this->context = 'COMMENT';
                    return;
                } else {
                    // A comment following any other context is a syntax error.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: "msgstr" was expected but not found on line %line.', $log_vars);
                    return false;
                }
                return;
            }
            if (!strncmp('msgid_plural', $line, 12)) {
                // A plural form for the current source string.
                if ($this->context != 'MSGID') {
                    // A plural form can only be added to an msgid directly.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: "msgid_plural" was expected but not found on line %line.', $log_vars);
                    return false;
                }
                // Remove 'msgid_plural' and trim away whitespace.
                $line = trim(substr($line, 12));
                // Only the plural source string is left, parse it.
                $quoted = $this->parse_quoted($line);
                if ($quoted === false) {
                    // The plural form must be wrapped in quotes.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains a syntax error on line %line.', $log_vars);
                    return false;
                }
                // Append the plural source to the current entry.
                if (is_string($this->current_item['msgid'])) {
                    // The first value was stored as string. Now we know the context is
                    // plural, it is converted to array.
                    $this->current_item['msgid'] = [$this->current_item['msgid']];
                }
                $this->current_item['msgid'][] = $quoted;
                $this->context = 'MSGID_PLURAL';
                return;
            }
            if (!strncmp('msgid', $line, 5)) {
                // Starting a new message.
                if ($this->context == 'MSGSTR' || $this->context == 'MSGSTR_ARR') {
                    // We are currently in string context, save current item.
                    $this->set_item_from_array($this->current_item);
                    // Start a new context for the msgid.
                    $this->current_item = [];
                } elseif ($this->context == 'MSGID') {
                    // We are currently already in the context, meaning we passed an id
                    // with no data.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: "msgid" is unexpected on line %line.', $log_vars);
                    return false;
                }
                // Remove 'msgid' and trim away whitespace.
                $line = trim(substr($line, 5));
                // Only the message id string is left, parse it.
                $quoted = $this->parse_quoted($line);
                if ($quoted === false) {
                    // The message id must be wrapped in quotes.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: invalid format for "msgid" on line %line.', $log_vars);
                    return false;
                }
                $this->current_item['msgid'] = $quoted;
                $this->context = 'MSGID';
                return;
            }
            if (!strncmp('msgctxt', $line, 7)) {
                // Starting a new context.
                if ($this->context == 'MSGSTR' || $this->context == 'MSGSTR_ARR') {
                    // We are currently in string context, save current item.
                    $this->set_item_from_array($this->current_item);
                    $this->current_item = [];
                } elseif (!empty($this->current_item['msgctxt'])) {
                    // A context cannot apply to another context.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: "msgctxt" is unexpected on line %line.', $log_vars);
                    return false;
                }
                // Remove 'msgctxt' and trim away whitespaces.
                $line = trim(substr($line, 7));
                // Only the msgctxt string is left, parse it.
                $quoted = $this->parse_quoted($line);
                if ($quoted === false) {
                    // The context string must be quoted.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: invalid format for "msgctxt" on line %line.', $log_vars);
                    return false;
                }
                $this->current_item['msgctxt'] = $quoted;
                $this->context = 'MSGCTXT';
                return;
            }
            if (!strncmp('msgstr[', $line, 7)) {
                // A message string for a specific plurality.
                if ($this->context != 'MSGID' && $this->context != 'MSGCTXT' && $this->context != 'MSGID_PLURAL' && $this->context != 'MSGSTR_ARR') {
                    // Plural message strings must come after msgid, msgctxt,
                    // msgid_plural, or other msgstr[] entries.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: "msgstr[]" is unexpected on line %line.', $log_vars);
                    return false;
                }
                // Ensure the plurality is terminated.
                if (!str_contains($line, ']')) {
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: invalid format for "msgstr[]" on line %line.', $log_vars);
                    return false;
                }
                // Extract the plurality.
                $from_bracket = strstr($line, '[');
                $this->current_plural_index = substr($from_bracket, 1, strpos($from_bracket, ']') - 1);
                // Skip to the next whitespace and trim away any further whitespace,
                // bringing $line to the message text only.
                $line = trim(strstr($line, ' '));
                $quoted = $this->parse_quoted($line);
                if ($quoted === false) {
                    // The string must be quoted.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: invalid format for "msgstr[]" on line %line.', $log_vars);
                    return false;
                }
                if (!isset($this->current_item['msgstr']) || !is_array($this->current_item['msgstr'])) {
                    $this->current_item['msgstr'] = [];
                }
                $this->current_item['msgstr'][$this->current_plural_index] = $quoted;
                $this->context = 'MSGSTR_ARR';
                return;
            }
            if (!strncmp('msgstr', $line, 6)) {
                // A string pair for an msgid (with optional context).
                if ($this->context != 'MSGID' && $this->context != 'MSGCTXT') {
                    // Strings are only valid within an id or context scope.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: "msgstr" is unexpected on line %line.', $log_vars);
                    return false;
                }
                // Remove 'msgstr' and trim away whitespaces.
                $line = trim(substr($line, 6));
                // Only the msgstr string is left, parse it.
                $quoted = $this->parse_quoted($line);
                if ($quoted === false) {
                    // The string must be quoted.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: invalid format for "msgstr" on line %line.', $log_vars);
                    return false;
                }
                $this->current_item['msgstr'] = $quoted;
                $this->context = 'MSGSTR';
                return;
            }
            if ($line != '') {
                // Anything that is not a token may be a continuation of a previous
                // token.
                $quoted = $this->parse_quoted($line);
                if ($quoted === false) {
                    // This string must be quoted.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: string continuation expected on line %line.', $log_vars);
                    return false;
                }
                // Append the string to the current item.
                if ($this->context == 'MSGID' || $this->context == 'MSGID_PLURAL') {
                    if (is_array($this->current_item['msgid'])) {
                        // Add string to last array element for plural sources.
                        $last_index = count($this->current_item['msgid']) - 1;
                        $this->current_item['msgid'][$last_index] .= $quoted;
                    } else {
                        // Singular source, just append the string.
                        $this->current_item['msgid'] .= $quoted;
                    }
                } elseif ($this->context == 'MSGCTXT') {
                    // Multiline context name.
                    $this->current_item['msgctxt'] .= $quoted;
                } elseif ($this->context == 'MSGSTR') {
                    // Multiline translation string.
                    $this->current_item['msgstr'] .= $quoted;
                } elseif ($this->context == 'MSGSTR_ARR') {
                    // Multiline plural translation string.
                    $this->current_item['msgstr'][$this->current_plural_index] .= $quoted;
                } else {
                    // No valid context to append to.
                    $this->errors[] = new Formattable_Markup('The translation stream %uri contains an error: unexpected string on line %line.', $log_vars);
                    return false;
                }
                return;
            }
        }
        // Empty line read or EOF of PO stream, close out the last entry.
        if ($this->context == 'MSGSTR' || $this->context == 'MSGSTR_ARR') {
            $this->set_item_from_array($this->current_item);
            $this->current_item = [];
        } elseif ($this->context != 'COMMENT') {
            $this->errors[] = new Formattable_Markup('The translation stream %uri ended unexpectedly at line %line.', $log_vars);
            return false;
        }
    }
    /**
     * Store the parsed values as a PoItem object.
     */
    public function set_item_from_array(array $value): void
    {
        $plural = false;
        $comments = '';
        if (isset($value['#'])) {
            $comments = $this->shorten_comments($value['#']);
        }
        if (is_array($value['msgstr'])) {
            // Sort plural variants by their form index.
            ksort($value['msgstr']);
            $plural = true;
        }
        $item = new Po_Item();
        $item->set_context($value['msgctxt'] ?? '');
        $item->set_source($value['msgid']);
        $item->set_translation($value['msgstr']);
        $item->set_plural($plural);
        $item->set_comment($comments);
        $item->set_langcode($this->langcode);
        $this->last_item = $item;
        $this->context = 'COMMENT';
    }
    /**
     * Parses a string in quotes.
     *
     * @param string $string
     *   A string specified with enclosing quotes.
     *
     * @return bool|string
     *   The string parsed from inside the quotes. False when the syntax is
     *   invalid.
     */
    public function parse_quoted($string): false|string
    {
        if (substr($string, 0, 1) != substr($string, -1, 1)) {
            // Start and end quotes must be the same.
            return false;
        }
        $quote = substr($string, 0, 1);
        $string = substr($string, 1, -1);
        if ($quote == '"') {
            // Double quotes: strip slashes.
            return stripcslashes($string);
        }
        if ($quote == "'") {
            // Simple quote: return as-is.
            return $string;
        }
        // Unrecognized quote.
        return false;
    }
    /**
     * Generates a short, one-string version of the passed comment array.
     *
     * @param string[] $comment
     *   An array of strings containing a comment.
     *
     * @return string
     *   Short one-string version of the comment.
     */
    private function shorten_comments($comment): string
    {
        $comm = '';
        while (count($comment)) {
            $test = $comm . substr(array_shift($comment), 1) . ', ';
            if (strlen($comm) < 130) {
                $comm = $test;
            } else {
                break;
            }
        }
        return trim(substr($comm, 0, -2));
    }
}