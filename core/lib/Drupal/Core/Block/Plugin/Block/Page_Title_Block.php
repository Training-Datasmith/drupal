<?php

declare (strict_types=1);
namespace Drupal\Core\Block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\Block_Base;
use Drupal\Core\Block\Title_Block_Plugin_Interface;
use Drupal\Core\String_Translation\Translatable_Markup;
/**
 * Provides a block to display the page title.
 */
#[Block(id: 'page_title_block', admin_label: new Translatable_Markup('Page title'), forms: ['settings_tray' => false])]
class Page_Title_Block extends Block_Base implements Title_Block_Plugin_Interface
{
    /**
     * The page title: a string (plain title) or a render array (formatted title).
     *
     * @var string|array
     */
    protected $title = '';
    /**
     * {@inheritdoc}
     */
    public function set_title($title): static
    {
        $this->title = $title;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function default_configuration(): array
    {
        return ['label_display' => '0'];
    }
    /**
     * {@inheritdoc}
     */
    public function build(): array
    {
        return ['#type' => 'page_title', '#title' => $this->title];
    }
}