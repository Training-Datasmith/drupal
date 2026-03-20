<?php

declare (strict_types=1);
namespace Drupal\Core\Block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\Block_Plugin_Interface;
use Drupal\Core\Block\Block_Plugin_Trait;
use Drupal\Core\Cache\Cacheable_Dependency_Trait;
use Drupal\Core\Form\Form_State_Interface;
use Drupal\Core\Plugin\Container_Factory_Plugin_Interface;
use Drupal\Core\Plugin\Plugin_Base;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Defines a fallback plugin for missing block plugins.
 */
#[Block(id: 'broken', admin_label: new Translatable_Markup('Broken/Missing'), category: new Translatable_Markup('Block'))]
class Broken extends Plugin_Base implements Block_Plugin_Interface, Container_Factory_Plugin_Interface
{
    use Block_Plugin_Trait;
    use Cacheable_Dependency_Trait;
    /**
     * Creates a Broken Block instance.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Session\AccountInterface $currentUser
     *   The current user.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, protected \Drupal\Core\Session\Account_Interface $current_user)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function build(): array
    {
        $build = [];
        if ($this->current_user->has_permission('administer blocks')) {
            $build += $this->broken_message();
        }
        return $build;
    }
    /**
     * {@inheritdoc}
     */
    public function block_form($form, Form_State_Interface $form_state)
    {
        return $this->broken_message();
    }
    /**
     * Generate message with debugging information as to why the block is broken.
     *
     * @return array
     *   Render array containing debug information.
     */
    protected function broken_message()
    {
        $build['message'] = ['#markup' => $this->t('This block is broken or missing. You may be missing content or you might need to install the original module.')];
        return $build;
    }
}