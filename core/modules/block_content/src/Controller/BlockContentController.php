<?php

namespace Drupal\block_content\Controller;

use Drupal\block_content\BlockContentTypeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller routines for custom block routes.
 */
class BlockContentController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('block_content'),
      $entity_type_manager->getStorage('block_content_type'),
      $container->get('theme_handler')
    );
  }

  /**
   * Constructs a BlockContent object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $blockContentStorage
   *   The content block storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $blockContentTypeStorage
   *   The block type storage.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   */
  public function __construct(protected \Drupal\Core\Entity\EntityStorageInterface $blockContentStorage, protected \Drupal\Core\Entity\EntityStorageInterface $blockContentTypeStorage, protected \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler)
  {
  }

  /**
   * Presents the content block creation form.
   *
   * @param \Drupal\block_content\BlockContentTypeInterface $block_content_type
   *   The block type to add.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   A form array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function addForm(BlockContentTypeInterface $block_content_type, Request $request) {
    $block = $this->blockContentStorage->create([
      'type' => $block_content_type->id(),
    ]);
    if (($theme = $request->query->get('theme')) && in_array($theme, array_keys($this->themeHandler->listInfo()))) {
      // We have navigated to this page from the block library and will keep
      // track of the theme for redirecting the user to the configuration page
      // for the newly created block in the given theme.
      $block->setTheme($theme);
    }
    return $this->entityFormBuilder()->getForm($block);
  }

  /**
   * Provides the page title for this controller.
   *
   * @param \Drupal\block_content\BlockContentTypeInterface $block_content_type
   *   The block type being added.
   *
   * @return string
   *   The page title.
   */
  public function getAddFormTitle(BlockContentTypeInterface $block_content_type): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Add %type content block', ['%type' => $block_content_type->label()]);
  }

}
