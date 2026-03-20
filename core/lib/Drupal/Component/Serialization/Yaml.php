<?php

declare (strict_types=1);
namespace Drupal\Component\Serialization;

use Drupal\Component\Serialization\Exception\Invalid_Data_Type_Exception;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;
/**
 * Provides a YAML serialization implementation using symfony/yaml.
 */
class Yaml implements Serialization_Interface
{
    /**
     * {@inheritdoc}
     */
    public static function encode($data)
    {
        try {
            // Set the indentation to 2 to match Drupal's coding standards.
            $yaml = new Dumper(2);
            return $yaml->dump($data, PHP_INT_MAX, 0, Symfony_Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Symfony_Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        } catch (\Exception $e) {
            throw new Invalid_Data_Type_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function decode($raw)
    {
        try {
            $yaml = new Parser();
            // Make sure we have a single trailing newline. A very simple config like
            // 'foo: bar' with no newline will fail to parse otherwise.
            return $yaml->parse($raw, Symfony_Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Symfony_Yaml::PARSE_CUSTOM_TAGS);
        } catch (\Exception $e) {
            throw new Invalid_Data_Type_Exception($e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * {@inheritdoc}
     */
    public static function get_file_extension(): string
    {
        return 'yml';
    }
}