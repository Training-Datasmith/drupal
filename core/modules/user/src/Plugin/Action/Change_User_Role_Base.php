<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for operations to change a user's role.
 */
abstract class ChangeUserRoleBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface
{
    use DependencyTrait;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, /**
   * The user role entity type.
   */
        protected \Drupal\Core\Entity\EntityTypeInterface $entityType)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager')->getDefinition('user_role')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration(): array
    {
        return [
          'rid' => '',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $roles = Role::loadMultiple();
        unset($roles[RoleInterface::ANONYMOUS_ID]);
        unset($roles[RoleInterface::AUTHENTICATED_ID]);
        $roles = array_map(fn (RoleInterface $role) => $role->label(), $roles);
        $form['rid'] = [
          '#type' => 'radios',
          '#title' => $this->t('Role'),
          '#options' => $roles,
          '#default_value' => $this->configuration['rid'],
          '#required' => true,
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        $this->configuration['rid'] = $form_state->getValue('rid');
    }

    /**
     * {@inheritdoc}
     */
    public function calculateDependencies()
    {
        if (!empty($this->configuration['rid'])) {
            $prefix = $this->entityType->getConfigPrefix() . '.';
            $this->addDependency('config', $prefix . $this->configuration['rid']);
        }
        return $this->dependencies;
    }

    /**
     * {@inheritdoc}
     */
    public function access($object, ?AccountInterface $account = null, $return_as_object = false)
    {
        /** @var \Drupal\user\UserInterface $object */
        $access = $object->access('update', $account, true)
          ->andIf($object->roles->access('edit', $account, true));

        return $return_as_object ? $access : $access->isAllowed();
    }

}
