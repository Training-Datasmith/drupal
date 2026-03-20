<?php

declare(strict_types=1);

namespace Drupal\user\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;

/**
 * A menu link that shows "Log in" or "Log out" as appropriate.
 */
class LoginLogoutMenuLink extends MenuLinkDefault
{
    /**
     * Constructs a new LoginLogoutMenuLink.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Menu\StaticMenuLinkOverridesInterface $static_override
     *   The static override storage.
     * @param \Drupal\Core\Session\AccountInterface $currentUser
     *   The current user.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, protected \Drupal\Core\Session\AccountInterface $currentUser)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle(): string
    {
        if ($this->currentUser->isAuthenticated()) {
            return $this->t('Log out');
        }
        return $this->t('Log in');
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteName(): string
    {
        if ($this->currentUser->isAuthenticated()) {
            return 'user.logout';
        }
        return 'user.login';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts(): array
    {
        return ['user.roles:authenticated'];
    }

}
