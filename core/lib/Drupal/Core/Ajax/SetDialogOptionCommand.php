<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

/**
 * Defines an AJAX command that sets jQuery UI dialog properties.
 *
 * @ingroup ajax
 */
class Set_Dialog_Option_Command implements Command_Interface
{
    /**
     * A CSS selector string.
     *
     * @var string
     */
    protected $selector;
    /**
     * Constructs a SetDialogOptionCommand object.
     *
     * @param string $selector
     *   The selector of the dialog whose title will be set. If set to an empty
     *   value, the default modal dialog will be selected.
     * @param string $optionName
     *   The name of the option to set. May be any jQuery UI dialog option.
     *   See http://api.jqueryui.com/dialog.
     * @param mixed $optionValue
     *   The value of the option to be passed to the dialog.
     */
    public function __construct(
        $selector,
        /**
         * A jQuery UI dialog option name.
         */
        protected $option_name,
        /**
         * A jQuery UI dialog option value.
         */
        protected $option_value
    )
    {
        $this->selector = $selector ?: '#drupal-modal';
    }
    /**
     * {@inheritdoc}
     */
    public function render(): array
    {
        return ['command' => 'setDialogOption', 'selector' => $this->selector, 'optionName' => $this->option_name, 'optionValue' => $this->option_value];
    }
}