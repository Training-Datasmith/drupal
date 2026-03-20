<?php

declare (strict_types=1);
namespace Drupal\Component\Front_Matter;

use Drupal\Component\Front_Matter\Exception\Front_Matter_Parse_Exception;
use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Drupal\Component\Serialization\Serialization_Interface;
/**
 * Component for parsing front matter from a source.
 *
 * This component allows for an easy and convenient way to parse
 * @link https://jekyllrb.com/docs/front-matter/ front matter @endlink
 * from a source.
 *
 * Front matter is used as a way to provide additional static data associated
 * with a source without affecting the contents of the source. Typically this
 * is used in templates to denote special handling or categorization.
 *
 * Front matter must be the first thing in the source and must take the form of
 * valid YAML set in between triple-hyphen lines:
 *
 * source.md:
 * @code
 * ---
 * important: true
 * ---
 * My content
 * @endcode
 *
 * example.php:
 * @code
 * use Drupal\Component\FrontMatter\FrontMatter;
 *
 * $frontMatter = FrontMatter::create(file_get_contents('source.md'));
 * $data = $frontMatter->getData(); // ['important' => TRUE]
 * $content = $frontMatter->getContent(); // 'My content'
 * $line => $frontMatter->getLine(); // 4, line where content actually starts.
 * @endcode
 *
 * @ingroup utility
 */
class Front_Matter
{
    /**
     * The separator used to indicate front matter data.
     *
     * @var string
     */
    public const SEPARATOR = '---';
    /**
     * The regular expression used to extract the YAML front matter content.
     *
     * @var string
     */
    public const REGEXP = '/\A(' . self::SEPARATOR . '(.*?)?\R' . self::SEPARATOR . ')(\R.*)?\Z/s';
    /**
     * The parsed source.
     *
     * @var array
     */
    protected $parsed;
    /**
     * A serializer.
     */
    protected string $serializer;
    /**
     * FrontMatter constructor.
     *
     * @param string $source
     *   A string source.
     * @param string $serializer
     *   The name of a class that implements
     *   \Drupal\Component\Serialization\SerializationInterface.
     */
    public function __construct(protected string $source, string $serializer = \Drupal\Component\Serialization\Yaml::class)
    {
        assert(is_subclass_of($serializer, Serialization_Interface::class), sprintf('The $serializer parameter must reference a class that implements %s.', Serialization_Interface::class));
        $this->serializer = $serializer;
    }
    /**
     * Creates a new FrontMatter instance.
     *
     * @param string $source
     *   A string source.
     * @param string $serializer
     *   The name of a class that implements
     *   \Drupal\Component\Serialization\SerializationInterface.
     */
    public static function create(string $source, string $serializer = \Drupal\Component\Serialization\Yaml::class): static
    {
        return new static($source, $serializer);
    }
    /**
     * Parses the source.
     *
     * @return array
     *   An associative array containing:
     *   - content: The real content.
     *   - data: The front matter data extracted and decoded.
     *   - line: The line number where the real content starts.
     *
     * @throws \Drupal\Component\FrontMatter\Exception\FrontMatterParseException
     */
    protected function parse(): array
    {
        if (!$this->parsed) {
            $content = $this->source;
            $data = [];
            $line = 1;
            // Parse front matter data.
            if (preg_match(static::REGEXP, $content, $matches)) {
                // Extract the source content.
                $content = !empty($matches[3]) ? trim($matches[3]) : '';
                // Extract the front matter data and typecast to an array to ensure
                // top level scalars are in an array.
                $raw = !empty($matches[2]) ? trim($matches[2]) : '';
                if ($raw) {
                    try {
                        $data = (array) $this->serializer::decode($raw);
                    } catch (Invalid_Data_Type_Exception $exception) {
                        // Rethrow a specific front matter parse exception.
                        throw new Front_Matter_Parse_Exception($exception);
                    }
                }
                // Determine the real source line by counting all newlines in the first
                // match (which includes the front matter separators) and append a new
                // line to denote that the content should start after it.
                if (!empty($matches[1])) {
                    $line += preg_match_all('/\R/', $matches[1] . "\n");
                }
            }
            // Set the parsed data.
            $this->parsed = ['content' => $content, 'data' => $data, 'line' => $line];
        }
        return $this->parsed;
    }
    /**
     * Retrieves the extracted source content.
     *
     * @return string
     *   The extracted source content.
     *
     * @throws \Drupal\Component\FrontMatter\Exception\FrontMatterParseException
     */
    public function get_content(): string
    {
        return $this->parse()['content'];
    }
    /**
     * Retrieves the extracted front matter data.
     *
     * @return array
     *   The extracted front matter data.
     *
     * @throws \Drupal\Component\FrontMatter\Exception\FrontMatterParseException
     */
    public function get_data(): array
    {
        return $this->parse()['data'];
    }
    /**
     * Retrieves the line where the source content starts, after any data.
     *
     * @return int
     *   The source content line.
     *
     * @throws \Drupal\Component\FrontMatter\Exception\FrontMatterParseException
     */
    public function get_line(): int
    {
        return $this->parse()['line'];
    }
}