<?php

declare (strict_types=1);
namespace Drupal\Component\Plugin\Exception;

/**
 * Defines a class for invalid plugin definition exceptions.
 */
class Invalid_Plugin_Definition_Exception extends Plugin_Exception
{
    /**
     * Constructs an InvalidPluginDefinitionException.
     *
     * @param string $pluginId
     *   The plugin ID of the mapper.
     * @param string $message
     *   The exception message.
     * @param int $code
     *   The exception code.
     * @param \Throwable|null $previous
     *   The previous throwable used for exception chaining.
     *
     * @see \Exception
     */
    public function __construct(
        /**
         * The plugin ID of the mapper.
         */
        protected $plugin_id,
        $message = '',
        $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
    /**
     * Gets the plugin ID of the mapper that raised the exception.
     *
     * @return string
     *   The plugin ID.
     */
    public function get_plugin_id()
    {
        return $this->plugin_id;
    }
}