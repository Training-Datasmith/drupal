<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Component\Render\Plain_Text_Output;
/**
 * Defines an AJAX command to open certain content in a dialog.
 *
 * @ingroup ajax
 */
class Open_Dialog_Command implements Command_Interface, Command_With_Attached_Assets_Interface
{
    use Command_With_Attached_Assets_Trait;
    /**
     * The title of the dialog.
     *
     * @var string
     */
    protected $title;
    /**
     * Stores dialog-specific options passed directly to jQuery UI dialogs.
     *
     * Any jQuery UI option can be used.
     *
     *
     * @see http://api.jqueryui.com/dialog.
     */
    protected array $dialog_options;
    /**
     * Constructs an OpenDialogCommand object.
     *
     * @param string $selector
     *   The selector of the dialog.
     * @param string|\Stringable|null $title
     *   The title of the dialog.
     * @param string|array $content
     *   The content that will be placed in the dialog, either a render array
     *   or an HTML string.
     * @param array $dialog_options
     *   (optional) Options to be passed to the dialog implementation. Any
     *   jQuery UI option can be used. See http://api.jqueryui.com/dialog.
     * @param array|null $settings
     *   (optional) Custom settings that will be passed to the Drupal behaviors
     *   on the content of the dialog. If left empty, the settings will be
     *   populated automatically from the current request.
     */
    public function __construct(
        /**
         * The selector of the dialog.
         */
        protected $selector,
        string|\Stringable|null $title,
        /**
         * The content for the dialog.
         *
         * Either a render array or an HTML string.
         */
        protected $content,
        array $dialog_options = [],
        /**
         * Custom settings passed to Drupal behaviors on the content of the dialog.
         */
        protected $settings = null
    )
    {
        $title = Plain_Text_Output::render_from_html($title);
        $dialog_options += ['title' => $title];
        $classes = [];
        if (isset($dialog_options['classes']['ui-dialog'])) {
            $classes[] = $dialog_options['classes']['ui-dialog'];
        }
        if ($classes) {
            $dialog_options['classes']['ui-dialog'] = implode(' ', $classes);
        }
        $this->dialog_options = $dialog_options;
    }
    /**
     * Returns the dialog options.
     *
     * @return array
     *   An array of the dialog-specific options passed directly to jQuery UI
     *   dialogs.
     */
    public function get_dialog_options()
    {
        return $this->dialog_options;
    }
    /**
     * Sets the dialog options array.
     *
     * @param array $dialog_options
     *   Options to be passed to the dialog implementation. Any jQuery UI option
     *   can be used. See http://api.jqueryui.com/dialog.
     */
    public function set_dialog_options($dialog_options): void
    {
        $this->dialog_options = $dialog_options;
    }
    /**
     * Sets a single dialog option value.
     *
     * @param string $key
     *   Key of the dialog option. Any jQuery UI option can be used.
     *   See http://api.jqueryui.com/dialog.
     * @param mixed $value
     *   Option to be passed to the dialog implementation.
     */
    public function set_dialog_option($key, $value): void
    {
        $this->dialog_options[$key] = $value;
    }
    /**
     * Sets the dialog title (an alias of setDialogOptions).
     *
     * @param string $title
     *   The new title of the dialog.
     */
    public function set_dialog_title($title): void
    {
        $this->set_dialog_option('title', $title);
    }
    /**
     * Implements \Drupal\Core\Ajax\CommandInterface:render().
     */
    public function render(): array
    {
        // For consistency ensure the modal option is set to TRUE or FALSE.
        $this->dialog_options['modal'] = isset($this->dialog_options['modal']) && $this->dialog_options['modal'];
        return ['command' => 'openDialog', 'selector' => $this->selector, 'settings' => $this->settings, 'data' => $this->get_rendered_content(), 'dialogOptions' => $this->dialog_options];
    }
}