<?php

declare(strict_types=1);

namespace Drupal\Core\Render\MainContent;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default main content renderer for Ajax requests.
 */
class AjaxRenderer implements MainContentRendererInterface
{
    /**
     * Constructs a new AjaxRenderer instance.
     *
     * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager
     *   The element info manager.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     */
    public function __construct(protected \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager, protected \Drupal\Core\Render\RendererInterface $renderer)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match): \Drupal\Core\Ajax\AjaxResponse
    {
        $response = new AjaxResponse();

        $html = $this->renderer->renderRoot($main_content);
        $response->setAttachments($main_content['#attached']);

        // The selector for the insert command is NULL as the new content will
        // replace the element making the Ajax call. The default 'replaceWith'
        // behavior can be changed with #ajax['method'].
        $response->addCommand(new InsertCommand(null, $html));
        $status_messages = ['#type' => 'status_messages'];
        $output = $this->renderer->renderRoot($status_messages);
        if (!empty($output)) {
            $response->addCommand(new PrependCommand(null, $output));
        }
        return $response;
    }

}
