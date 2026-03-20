<?php

declare (strict_types=1);
namespace Drupal\Core\Asset;

use Drupal\Core\File\File_Url_Generator_Interface;
/**
 * Renders CSS assets.
 */
class Css_Collection_Renderer implements Asset_Collection_Renderer_Interface
{
    /**
     * Constructs a CssCollectionRenderer.
     *
     * @param \Drupal\Core\Asset\AssetQueryStringInterface $assetQueryString
     *   The asset query string.
     * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
     *   The file URL generator.
     */
    public function __construct(protected Asset_Query_String_Interface $asset_query_string, protected File_Url_Generator_Interface $file_url_generator)
    {
    }
    /**
     * {@inheritdoc}
     * @return array{'#type': 'html_tag', '#tag': 'link', '#attributes': non-empty-array}[]
     */
    public function render(array $css_assets): array
    {
        $elements = [];
        // Defaults for LINK and STYLE elements.
        $link_element_defaults = ['#type' => 'html_tag', '#tag' => 'link', '#attributes' => ['rel' => 'stylesheet']];
        foreach ($css_assets as $css_asset) {
            $element = $link_element_defaults;
            $element['#attributes']['media'] = $css_asset['media'];
            switch ($css_asset['type']) {
                // For file items, output a LINK tag for file CSS assets.
                case 'file':
                    $element['#attributes']['href'] = $this->file_url_generator->generate_string($css_asset['data']);
                    // For unaggregated assets, add a query string to force edge/browser
                    // cache invalidation. This query string is updated after each full
                    // cache clear.
                    if (!isset($css_asset['preprocessed'])) {
                        $query_string_separator = str_contains((string) $css_asset['data'], '?') ? '&' : '?';
                        $element['#attributes']['href'] .= $query_string_separator . $this->asset_query_string->get();
                    }
                    break;
                case 'external':
                    $element['#attributes']['href'] = $css_asset['data'];
                    break;
                default:
                    throw new \Exception('Invalid CSS asset type.');
            }
            // Merge any additional attributes.
            if (!empty($css_asset['attributes'])) {
                $element['#attributes'] += $css_asset['attributes'];
            }
            $elements[] = $element;
        }
        return $elements;
    }
}