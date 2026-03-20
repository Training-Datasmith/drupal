<?php

declare (strict_types=1);
namespace Drupal\Core\Block;

use Drupal\Component\Plugin\Categorizing_Plugin_Manager_Interface;
use Drupal\Core\Plugin\Context\Context_Aware_Plugin_Manager_Interface;
use Drupal\Core\Plugin\Filtered_Plugin_Manager_Interface;
/**
 * Provides an interface for the discovery and instantiation of block plugins.
 */
interface Block_Manager_Interface extends Context_Aware_Plugin_Manager_Interface, Categorizing_Plugin_Manager_Interface, Filtered_Plugin_Manager_Interface
{
}