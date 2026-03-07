<?php

declare(strict_types=1);

namespace Drupal\system\Form;

use Drupal\Core\Config\Entity\ConfigDependencyDeleteFormTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a confirmation form to uninstall selected modules.
 *
 * @internal
 */
class ModulesUninstallConfirmForm extends ConfirmFormBase
{
    use ConfigDependencyDeleteFormTrait;

    /**
     * An array of modules to uninstall.
     *
     * @var array
     */
    protected $modules = [];

    /**
     * Constructs a ModulesUninstallConfirmForm object.
     *
     * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
     *   The module installer.
     * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $keyValueExpirable
     *   The key value expirable factory.
     * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
     *   The configuration manager.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     *   The entity type manager.
     * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
     *   The module extension list.
     * @param string|false|null $installProfile
     *   The install profile.
     */
    public function __construct(protected \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller, protected \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $keyValueExpirable, protected \Drupal\Core\Config\ConfigManagerInterface $configManager, protected \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager, protected \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList, protected string|false|null $installProfile = null)
    {
        $this->installProfile ??= \Drupal::installProfile();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('module_installer'),
            $container->get('keyvalue.expirable')->get('modules_uninstall'),
            $container->get('config.manager'),
            $container->get('entity_type.manager'),
            $container->get('extension.list.module'),
            $container->getParameter('install_profile')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Confirm uninstall');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Uninstall');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): \Drupal\Core\Url
    {
        return new Url('system.modules_uninstall');
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        return $this->t('Would you like to continue with uninstalling the above?');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'system_modules_uninstall_confirm_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        // Retrieve the list of modules from the key value store.
        $account = $this->currentUser()->id();
        $this->modules = $this->keyValueExpirable->get($account);

        // Prevent this page from showing when the module list is empty.
        if (empty($this->modules)) {
            $this->messenger()->addError($this->t('The selected modules could not be uninstalled, either due to a website problem or due to the uninstall confirmation form timing out.'));
            return $this->redirect('system.modules_uninstall');
        }

        $data = $this->moduleExtensionList->getList();
        $form['text']['#markup'] = '<p>' . $this->t('The following modules will be completely uninstalled from your site, and <em>all data from these modules will be lost</em>!') . '</p>';
        $form['modules'] = [
          '#theme' => 'item_list',
          '#items' => array_map(fn ($module) => $data[$module]->info['name'], $this->modules),
        ];

        if (!empty($this->installProfile) && in_array($this->installProfile, $this->modules, true)) {
            $form['profile']['#markup'] = '<p>' . $this->t('Once uninstalled, the %install_profile profile cannot be reinstalled.', ['%install_profile' => $data[$this->installProfile]->info['name']]) . '</p>';
        }

        // List the dependent entities.
        $this->addDependencyListsToForm($form, 'module', $this->modules, $this->configManager, $this->entityTypeManager);

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        // Clear the key value store entry.
        $account = $this->currentUser()->id();
        $this->keyValueExpirable->delete($account);

        // Uninstall the modules.
        $this->moduleInstaller->uninstall($this->modules);

        $this->messenger()->addStatus($this->t('The selected modules have been uninstalled.'));
        $form_state->setRedirectUrl($this->getCancelUrl());
    }

}
