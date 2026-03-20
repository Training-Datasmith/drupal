<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * Base command that only exists to simplify AJAX commands.
 */
class Base_Command implements Command_Interface
{
    /**
     * Constructs a BaseCommand object.
     *
     * @param string $command
     *   The name of the command.
     * @param string $data
     *   The data to pass on to the client side.
     */
    public function __construct(
        /**
         * The name of the command.
         */
        protected $command,
        /**
         * The data to pass on to the client side.
         */
        protected $data
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return ['command' => $this->command, 'data' => $this->data];
    }
}