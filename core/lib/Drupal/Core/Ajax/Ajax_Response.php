<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Core\Render\Attachments_Interface;
use Drupal\Core\Render\Attachments_Trait;
use Drupal\Core\Render\Bubbleable_Metadata;
use Symfony\Component\Http_Foundation\Json_Response;
/**
 * JSON response object for AJAX requests.
 *
 * @ingroup ajax
 */
class Ajax_Response extends Json_Response implements Attachments_Interface
{
    use Attachments_Trait;
    /**
     * The array of ajax commands.
     *
     * @var array
     */
    protected $commands = [];
    /**
     * Add an AJAX command to the response.
     *
     * @param \Drupal\Core\Ajax\CommandInterface $command
     *   An AJAX command object implementing CommandInterface.
     * @param bool $prepend
     *   A boolean which determines whether the new command should be executed
     *   before previously added commands. Defaults to FALSE.
     *
     * @return $this
     *   The current AjaxResponse.
     */
    public function add_command(Command_Interface $command, $prepend = false)
    {
        if ($prepend) {
            array_unshift($this->commands, $command->render());
        } else {
            $this->commands[] = $command->render();
        }
        if ($command instanceof Command_With_Attached_Assets_Interface) {
            $assets = $command->get_attached_assets();
            $attachments = ['library' => $assets->get_libraries(), 'drupalSettings' => $assets->get_settings()];
            $attachments = Bubbleable_Metadata::merge_attachments($this->get_attachments(), $attachments);
            $this->set_attachments($attachments);
        }
        return $this;
    }
    /**
     * Merges other ajax response with this one.
     *
     * Adds commands and merges attachments from the other ajax response.
     *
     * @param \Drupal\Core\Ajax\AjaxResponse $other
     *   An AJAX response to merge.
     *
     * @return $this
     *   Returns this after merging.
     */
    public function merge_with(Ajax_Response $other): Ajax_Response
    {
        $this->commands = array_merge($this->get_commands(), $other->get_commands());
        $this->attachments = Bubbleable_Metadata::merge_attachments($this->get_attachments(), $other->get_attachments());
        return $this;
    }
    /**
     * Gets all AJAX commands.
     *
     * @return array
     *   Returns render arrays for all previously added commands.
     */
    public function &get_commands()
    {
        return $this->commands;
    }
}