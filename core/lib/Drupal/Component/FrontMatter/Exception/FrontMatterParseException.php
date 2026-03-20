<?php

declare (strict_types=1);
namespace Drupal\Component\Front_Matter\Exception;

use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
/**
 * Defines a class for front matter parsing exceptions.
 */
class Front_Matter_Parse_Exception extends Invalid_Data_Type_Exception
{
    /**
     * The line number of where the parse error occurred.
     *
     * This line number is in relation to where the parse error occurred in the
     * source front matter content. It is different from \Exception::getLine()
     * which is populated with the line number of where this exception was
     * thrown in PHP.
     */
    protected int $source_line;
    /**
     * Constructs a new FrontMatterParseException instance.
     *
     * @param \Drupal\Component\Serialization\Exception\InvalidDataTypeException $exception
     *   The exception thrown when attempting to parse front matter data.
     */
    public function __construct(Invalid_Data_Type_Exception $exception)
    {
        $this->source_line = 1;
        // Attempt to extract the line number from the serializer error. This isn't
        // a very stable way to do this, however it is the only way given that
        // \Drupal\Component\Serialization\SerializationInterface does not have
        // methods for accessing this kind of information reliably.
        $message = 'An error occurred when attempting to parse front matter data';
        if ($exception) {
            preg_match('/line:?\s?(\d+)/i', $exception->get_message(), $matches);
            if (!empty($matches[1])) {
                $message .= ' on line %d';
                // Add any matching line count to the existing source line so it
                // increases it by 1 to account for the front matter separator (---).
                $this->source_line += (int) $matches[1];
            }
        }
        parent::__construct(sprintf($message, $this->source_line), 0, $exception);
    }
    /**
     * Retrieves the line number where the parse error occurred.
     *
     * This line number is in relation to where the parse error occurred in the
     * source front matter content. It is different from \Exception::getLine()
     * which is populated with the line number of where this exception was
     * thrown in PHP.
     *
     * @return int
     *   The source line number.
     */
    public function get_source_line(): int
    {
        return $this->source_line;
    }
}