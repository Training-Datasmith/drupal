<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

/**
 * The default attached assets collection.
 */
class Attached_Assets implements Attached_Assets_Interface
{
    /**
     * The (ordered) list of asset libraries attached to the current response.
     *
     * @var string[]
     */
    public $libraries = [];
    /**
     * The JavaScript settings attached to the current response.
     *
     * @var array
     */
    public $settings = [];
    /**
     * The set of asset libraries that the client has already loaded.
     *
     * @var string[]
     */
    protected $already_loaded_libraries = [];
    /**
     * {@inheritdoc}
     */
    public static function create_from_render_array(array $render_array): static
    {
        if (!isset($render_array['#attached'])) {
            throw new \LogicException('The render array has not yet been rendered, hence not all attachments have been collected yet.');
        }
        $assets = new static();
        if (isset($render_array['#attached']['library'])) {
            $assets->set_libraries($render_array['#attached']['library']);
        }
        if (isset($render_array['#attached']['drupalSettings'])) {
            $assets->set_settings($render_array['#attached']['drupalSettings']);
        }
        return $assets;
    }
    /**
     * {@inheritdoc}
     */
    public function set_libraries(array $libraries): static
    {
        $this->libraries = array_unique($libraries);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_libraries()
    {
        return $this->libraries;
    }
    /**
     * {@inheritdoc}
     */
    public function set_settings(array $settings): static
    {
        $this->settings = $settings;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_settings()
    {
        return $this->settings;
    }
    /**
     * {@inheritdoc}
     */
    public function get_already_loaded_libraries()
    {
        return $this->already_loaded_libraries;
    }
    /**
     * {@inheritdoc}
     */
    public function set_already_loaded_libraries(array $libraries): static
    {
        $this->already_loaded_libraries = $libraries;
        return $this;
    }
}