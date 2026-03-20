<?php

declare (strict_types=1);
namespace Drupal\Core\Display;

use Drupal\Component\Plugin\Configurable_Interface;
use Drupal\Component\Plugin\Dependent_Plugin_Interface;
use Drupal\Component\Plugin\Plugin_Inspection_Interface;
use Drupal\Core\Cache\Refinable_Cacheable_Dependency_Interface;
use Drupal\Core\Plugin\Plugin_Form_Interface;
use Drupal\Core\Session\Account_Interface;
/**
 * Provides an interface for DisplayVariant plugins.
 *
 * @see \Drupal\Core\Display\Attribute\DisplayVariant
 * @see \Drupal\Core\Display\VariantBase
 * @see \Drupal\Core\Display\VariantManager
 * @see plugin_api
 */
interface Variant_Interface extends Plugin_Inspection_Interface, Configurable_Interface, Dependent_Plugin_Interface, Plugin_Form_Interface, Refinable_Cacheable_Dependency_Interface
{
    /**
     * Returns the user-facing display variant label.
     *
     * @return string
     *   The display variant label.
     */
    public function label();
    /**
     * Returns the admin-facing display variant label.
     *
     * This is for the type of display variant, not the configured variant itself.
     *
     * @return string
     *   The display variant administrative label.
     */
    public function admin_label();
    /**
     * Returns the unique ID for the display variant.
     *
     * @return string
     *   The display variant ID.
     */
    public function id();
    /**
     * Returns the weight of the display variant.
     *
     * @return int
     *   The display variant weight.
     */
    public function get_weight();
    /**
     * Sets the weight of the display variant.
     *
     * @param int $weight
     *   The weight to set.
     */
    public function set_weight($weight);
    /**
     * Determines if this display variant is accessible.
     *
     * @param \Drupal\Core\Session\AccountInterface $account
     *   (optional) The user for which to check access, or NULL to check access
     *   for the current user. Defaults to NULL.
     *
     * @return bool
     *   TRUE if this display variant is accessible, FALSE otherwise.
     */
    public function access(?Account_Interface $account = null);
    /**
     * Builds and returns the renderable array for the display variant.
     *
     * The variant can contain cacheability metadata for the configuration that
     * was passed in setConfiguration(). In the build() method, this should be
     * added to the render array that is returned.
     *
     * @return array
     *   A render array for the display variant.
     */
    public function build();
}