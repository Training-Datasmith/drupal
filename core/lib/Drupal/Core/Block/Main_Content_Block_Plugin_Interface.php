<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

/**
 * The interface for "main page content" blocks.
 *
 * A main page content block represents the content returned by the controller.
 *
 * @ingroup block_api
 */
interface Main_Content_Block_Plugin_Interface extends Block_Plugin_Interface
{
    /**
     * Sets the main content render array.
     *
     * @param array $main_content
     *   The render array representing the main content.
     */
    public function set_main_content(array $main_content);
}