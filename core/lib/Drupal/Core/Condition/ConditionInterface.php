<?php

declare (strict_types=1);
namespace Drupal\Core\Condition;

use Drupal\Component\Plugin\Configurable_Interface;
use Drupal\Component\Plugin\Dependent_Plugin_Interface;
use Drupal\Component\Plugin\Plugin_Inspection_Interface;
use Drupal\Core\Cache\Cacheable_Dependency_Interface;
use Drupal\Core\Executable\Executable_Interface;
use Drupal\Core\Executable\Executable_Manager_Interface;
use Drupal\Core\Plugin\Plugin_Form_Interface;
/**
 * An interface for condition plugins.
 *
 * Condition plugins are context-aware and configurable. They support the
 * following keys in their plugin definitions:
 * - context: An array of context definitions, keyed by context name. Each
 *   context definition is a typed data definition describing the context. Check
 *   the typed data definition docs for details.
 * - configuration: An array of configuration option definitions, keyed by
 *   option name. Each option definition is a typed data definition describing
 *   the configuration option. Check the typed data definition docs for details.
 *
 * @todo Replace the dependency on \Drupal\Core\Form\FormInterface with a new
 *   interface from https://www.drupal.org/node/2006248.
 * @todo WARNING: The condition API is going to receive some additions before release.
 * The following additions are likely to happen:
 *  - The way configuration is handled and configuration forms are built is
 *    likely to change in order for the plugin to be of use for Rules.
 *  - Conditions will receive a data processing API that allows for token
 *    replacements to happen outside of the plugin implementations,
 *    see https://www.drupal.org/node/2347023.
 *  - Conditions will have to implement access control for checking who is
 *    allowed to configure or perform the action at
 *    https://www.drupal.org/node/2172017.
 *
 * @see \Drupal\Core\TypedData\TypedDataManager::create()
 * @see \Drupal\Core\Executable\ExecutableInterface
 * @see \Drupal\Core\Condition\ConditionManager
 * @see \Drupal\Core\Condition\Annotation\Condition
 * @see \Drupal\Core\Condition\Attribute\Condition
 * @see \Drupal\Core\Condition\ConditionPluginBase
 *
 * @ingroup plugin_api
 */
interface Condition_Interface extends Executable_Interface, Plugin_Form_Interface, Configurable_Interface, Dependent_Plugin_Interface, Plugin_Inspection_Interface, Cacheable_Dependency_Interface
{
    /**
     * Determines whether condition result will be negated.
     *
     * @return bool
     *   Whether the condition result will be negated.
     */
    public function is_negated();
    /**
     * Evaluates the condition and returns TRUE or FALSE accordingly.
     *
     * @return bool
     *   TRUE if the condition has been met, FALSE otherwise.
     */
    public function evaluate();
    /**
     * Provides a human readable summary of the condition's configuration.
     */
    public function summary();
    /**
     * Sets the executable manager class.
     *
     * @param \Drupal\Core\Executable\ExecutableManagerInterface $executableManager
     *   The executable manager.
     */
    public function set_executable_manager(Executable_Manager_Interface $executable_manager);
}