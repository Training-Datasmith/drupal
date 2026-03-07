<?php

declare(strict_types=1);

namespace Drupal\system\Controller;

use Drupal\Core\Asset\AssetGroupSetHashTrait;
use Drupal\Core\Asset\AttachedAssetsInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to serve CSS aggregates.
 */
class CssAssetController extends AssetControllerBase
{
    use AssetGroupSetHashTrait;

    /**
     * {@inheritdoc}
     */
    protected string $contentType = 'text/css';

    /**
     * {@inheritdoc}
     */
    protected string $assetType = 'css';

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('stream_wrapper_manager'),
            $container->get('library.dependency_resolver'),
            $container->get('asset.resolver'),
            $container->get('theme.initialization'),
            $container->get('theme.manager'),
            $container->get('asset.css.collection_grouper'),
            $container->get('asset.css.collection_optimizer'),
            $container->get('asset.css.dumper'),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getGroups(AttachedAssetsInterface $attached_assets, Request $request): array
    {
        $language = $this->languageManager()->getLanguage($request->query->get('language'));
        $assets = $this->assetResolver->getCssAssets($attached_assets, false, $language);
        return $this->grouper->group($assets);
    }

}
