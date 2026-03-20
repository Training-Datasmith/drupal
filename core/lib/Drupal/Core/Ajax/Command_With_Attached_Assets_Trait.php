<?php

declare (strict_types=1);
namespace Drupal\Core\Ajax;

use Drupal\Core\Asset\Attached_Assets;
/**
 * Trait for Ajax commands that render content and attach assets.
 *
 * @ingroup ajax
 */
trait Command_With_Attached_Assets_Trait
{
    /**
     * The attached assets for this Ajax command.
     *
     * @var \Drupal\Core\Asset\AttachedAssets
     */
    protected $attached_assets;
    /**
     * Processes the content for output.
     *
     * If content is a render array, it may contain attached assets to be
     * processed.
     *
     * @return string|\Drupal\Component\Render\MarkupInterface
     *   HTML rendered content.
     */
    protected function get_rendered_content()
    {
        $this->attached_assets = new Attached_Assets();
        if (is_array($this->content)) {
            if (!$this->content) {
                return '';
            }
            $html = \Drupal::service('renderer')->render_root($this->content);
            $this->attached_assets = Attached_Assets::create_from_render_array($this->content);
            return $html;
        }
        return $this->content;
    }
    /**
     * Gets the attached assets.
     *
     * @return \Drupal\Core\Asset\AttachedAssets|null
     *   The attached assets for this command.
     */
    public function get_attached_assets()
    {
        return $this->attached_assets;
    }
}