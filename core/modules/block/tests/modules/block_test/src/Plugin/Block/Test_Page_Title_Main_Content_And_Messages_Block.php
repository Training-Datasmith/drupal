<?php

declare(strict_types=1);

namespace Drupal\block_test\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\MainContentBlockPluginInterface;
use Drupal\Core\Block\MessagesBlockPluginInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block which includes page title, main content & messages together.
 */
#[Block(
    id: 'test_title_content_message_block',
    admin_label: new TranslatableMarkup('Test Page Title, Content, and Message Block'),
)]
class TestPageTitleMainContentAndMessagesBlock extends BlockBase implements MainContentBlockPluginInterface, TitleBlockPluginInterface, MessagesBlockPluginInterface
{
    /**
     * The page title: a string (plain title) or a render array (formatted title).
     *
     * @var string|array
     */
    protected $title = '';

    /**
     * The render array representing the main page content.
     *
     * @var array
     */
    protected $mainContent;

    /**
     * Whether setMainContent was called.
     *
     * @var bool
     */
    protected $isMainContentPlaced = false;

    /**
     * Whether setTitle was called.
     *
     * @var bool
     */
    protected $isPageTitlePlaced = false;

    /**
     * {@inheritdoc}
     */
    public function setMainContent(array $main_content): void
    {
        $this->mainContent = $main_content;
        $this->isMainContentPlaced = true;
    }

    /**
     * {@inheritdoc}
     */
    public function setTitle($title): static
    {
        $this->title = $title;
        $this->isPageTitlePlaced = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function build(): array
    {
        $build = [];
        // Print a message to later verify that messages are rendered in the block.
        $this->messenger()->addStatus('This is a status message.');

        if ($this->isPageTitlePlaced === true) {
            $build['page_title'] = [
              '#type' => 'page_title',
              '#title' => $this->title,
            ];
            // Display text which confirms title was displayed by this block.
            // Content of text is used in corresponding test.
            $build['page_title_confirmation'] = [
              '#markup' => $this->t('Page title has been placed in the block.'),
            ];
        }

        if ($this->isMainContentPlaced === true) {
            $build['main_content'] = $this->mainContent;
            // Display text which confirms main content was displayed by this block.
            // Content of text is used in corresponding test.
            $build['main_content_confirmation'] = [
              '#markup' => $this->t('Main content has been placed in the block.'),
            ];
        }

        $build['content']['messages'] = [
          '#prefix' => '<div id="test-block-messages-wrapper">',
          '#weight' => -1000,
          '#type' => 'status_messages',
          '#include_fallback' => true,
          '#suffix' => '</div>',
        ];

        return $build;
    }

}
