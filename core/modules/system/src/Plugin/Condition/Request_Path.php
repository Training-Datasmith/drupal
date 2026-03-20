<?php

declare(strict_types=1);

namespace Drupal\system\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Request Path' condition.
 */
#[Condition(
    id: 'request_path',
    label: new TranslatableMarkup('Request Path'),
)]
class RequestPath extends ConditionPluginBase implements ContainerFactoryPluginInterface
{
    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * Constructs a RequestPath condition plugin.
     *
     * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
     *   An alias manager to find the alias for the current system path.
     * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
     *   The path matcher service.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     * @param \Drupal\Core\Path\CurrentPathStack $currentPath
     *   The current path.
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin ID for the plugin instance.
     * @param array $plugin_definition
     *   The plugin implementation definition.
     */
    public function __construct(protected \Drupal\path_alias\AliasManagerInterface $aliasManager, protected \Drupal\Core\Path\PathMatcherInterface $pathMatcher, RequestStack $request_stack, protected \Drupal\Core\Path\CurrentPathStack $currentPath, array $configuration, $plugin_id, array $plugin_definition)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->requestStack = $request_stack;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static
    {
        return new static(
            $container->get('path_alias.manager'),
            $container->get('path.matcher'),
            $container->get('request_stack'),
            $container->get('path.current'),
            $configuration,
            $plugin_id,
            $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return ['pages' => ''] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['pages'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Pages'),
          '#default_value' => $this->configuration['pages'],
          '#description' => $this->t("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. An example path is %user-wildcard for every user page. %front is the front page.", [
            '%user-wildcard' => '/user/*',
            '%front' => '<front>',
          ]),
        ];
        return parent::buildConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        $paths = array_map(trim(...), explode("\n", (string) $form_state->getValue('pages')));
        foreach ($paths as $path) {
            if (empty($path)) {
                continue;
            }
            if ($path === '<front>') {
                continue;
            }
            if (str_starts_with($path, '/')) {
                continue;
            }
            $form_state->setErrorByName('pages', $this->t('The path %path requires a leading forward slash when used with the Pages setting.', ['%path' => $path]));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
    {
        $this->configuration['pages'] = $form_state->getValue('pages');
        parent::submitConfigurationForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function summary(): \Drupal\Core\StringTranslation\TranslatableMarkup
    {
        if (empty($this->configuration['pages'])) {
            return $this->t('No page is specified');
        }
        $pages = array_map(trim(...), explode("\n", (string) $this->configuration['pages']));
        $pages = implode(', ', $pages);
        if (!empty($this->configuration['negate'])) {
            return $this->t('Do not return true on the following pages: @pages', ['@pages' => $pages]);
        }
        return $this->t('Return true on the following pages: @pages', ['@pages' => $pages]);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate()
    {
        // Convert path to lowercase. This allows comparison of the same path
        // with different case. Ex: /Page, /page, /PAGE.
        $pages = mb_strtolower((string) $this->configuration['pages']);
        if (!$pages) {
            return true;
        }

        $request = $this->requestStack->getCurrentRequest();
        // Compare the lowercase path alias (if any) and internal path.
        $path = $this->currentPath->getPath($request);
        // Do not trim a trailing slash if that is the complete path.
        $path = $path === '/' ? $path : rtrim($path, '/');
        $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));
        if ($this->pathMatcher->matchPath($path_alias, $pages)) {
            return true;
        }
        return ($path != $path_alias) && $this->pathMatcher->matchPath($path, $pages);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts(): array
    {
        $contexts = parent::getCacheContexts();
        $contexts[] = 'url.path';
        return $contexts;
    }

}
