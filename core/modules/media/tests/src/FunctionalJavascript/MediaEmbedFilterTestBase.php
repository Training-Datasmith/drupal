<?php

declare(strict_types=1);

namespace Drupal\Tests\media\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;

/**
 * Base class for media embed filter configuration tests.
 */
abstract class MediaEmbedFilterTestBase extends MediaJavascriptTestBase
{
    /**
     * {@inheritdoc}
     */
    protected $defaultTheme = 'stark';

    /**
     * {@inheritdoc}
     *
     * @todo Remove this class property in https://www.drupal.org/node/3091878/.
     */
    protected $failOnJavascriptConsoleErrors = false;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Necessary for code coverage to work.
        require_once __DIR__ . '/../../../media.module';
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $format = FilterFormat::create([
          'format' => 'media_embed_test',
          'name' => 'Test format',
          'filters' => [],
        ]);
        $format->save();

        $this->drupalLogin($this->drupalCreateUser([
          'administer filters',
          $format->getPermissionName(),
        ]));
    }

    /**
     * Data provider for testing validation when adding and editing media embeds.
     */
    public static function providerTestValidations(): array
    {
        return [
          'Tests that no filter_html occurs when filter_html not enabled.' => [
            'filter_html_status' => false,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => true,
            'allowed_html' => false,
            'expected_error_message' => false,
          ],
          'Tests validation when both filter_html and media_embed are disabled.' => [
            'filter_html_status' => false,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => false,
            'allowed_html' => false,
            'expected_error_message' => false,
          ],
          'Tests validation when media_embed filter not enabled and filter_html is enabled.' => [
            'filter_html_status' => true,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => false,
            'allowed_html' => 'default',
            'expected_error_message' => false,
          ],
          'Tests validation when drupal-media element has no attributes.' => [
            'filter_html_status' => true,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => true,
            'allowed_html' => "<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type='1 A I'> <li> <dl> <dt> <dd> <h2 id='jump-*'> <h3 id> <h4 id> <h5 id> <h6 id> <drupal-media>",
            'expected_error_message' => 'The <drupal-media> tag in the allowed HTML tags is missing the following attributes: data-entity-type, data-entity-uuid.',
          ],
          'Tests validation when drupal-media element lacks some required attributes.' => [
            'filter_html_status' => true,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => true,
            'allowed_html' => "<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type='1 A I'> <li> <dl> <dt> <dd> <h2 id='jump-*'> <h3 id> <h4 id> <h5 id> <h6 id> <drupal-media data-entity-uuid data-align>",
            'expected_error_message' => 'The <drupal-media> tag in the allowed HTML tags is missing the following attributes: data-entity-type.',
          ],
          'Tests validation when both filter_html and media_embed are enabled and configured correctly' => [
            'filter_html_status' => true,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => true,
            'allowed_html' => "<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type='1 A I'> <li> <dl> <dt> <dd> <h2 id='jump-*'> <h3 id> <h4 id> <h5 id> <h6 id> <drupal-media data-entity-type data-entity-uuid data-view-mode>",
            'expected_error_message' => false,
          ],
          'Order validation: media_embed before all filters' => [
            'filter_html_status' => true,
            'filter_align_status' => true,
            'filter_caption_status' => true,
            'filter_html_image_secure_status' => true,
            'media_embed' => '-5',
            'allowed_html' => "<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type='1 A I'> <li> <dl> <dt> <dd> <h2 id='jump-*'> <h3 id> <h4 id> <h5 id> <h6 id> <drupal-media data-entity-type data-entity-uuid data-view-mode>",
            'expected_error_message' => 'The Embed media filter needs to be placed after the following filters: Align images, Caption images, Restrict images to this site.',
          ],
          'Order validation: media_embed before filter_align' => [
            'filter_html_status' => false,
            'filter_align_status' => true,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => false,
            'media_embed' => '-5',
            'allowed_html' => '',
            'expected_error_message' => 'The Embed media filter needs to be placed after the Align images filter.',
          ],
          'Order validation: media_embed before filter_caption' => [
            'filter_html_status' => false,
            'filter_align_status' => false,
            'filter_caption_status' => true,
            'filter_html_image_secure_status' => false,
            'media_embed' => '-5',
            'allowed_html' => '',
            'expected_error_message' => 'The Embed media filter needs to be placed after the Caption images filter.',
          ],
          'Order validation: media_embed before filter_html_image_secure' => [
            'filter_html_status' => false,
            'filter_align_status' => false,
            'filter_caption_status' => false,
            'filter_html_image_secure_status' => true,
            'media_embed' => '-5',
            'allowed_html' => '',
            'expected_error_message' => 'The Embed media filter needs to be placed after the Restrict images to this site filter.',
          ],
          'Order validation: media_embed after filter_align and filter_caption but before filter_html_image_secure' => [
            'filter_html_status' => true,
            'filter_align_status' => true,
            'filter_caption_status' => true,
            'filter_html_image_secure_status' => true,
            'media_embed' => '5',
            'allowed_html' => "<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type='1 A I'> <li> <dl> <dt> <dd> <h2 id='jump-*'> <h3 id> <h4 id> <h5 id> <h6 id> <drupal-media data-entity-type data-entity-uuid data-view-mode>",
            'expected_error_message' => 'The Embed media filter needs to be placed after the Restrict images to this site filter.',
          ],
        ];
    }

    /**
     * Show visually hidden fields.
     */
    protected function showHiddenFields()
    {
        $script = <<<JS
      var hidden_fields = document.querySelectorAll(".hidden");

      [].forEach.call(hidden_fields, function(el) {
        el.classList.remove("hidden");
      });
JS;

        $this->getSession()->executeScript($script);
    }

}
