<?php

declare(strict_types=1);

namespace Drupal\update\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\update\UpdateFetcherInterface;

/**
 * Controller routines for update routes.
 */
class UpdateController extends ControllerBase
{
    /**
     * Constructs update status data.
     *
     * @param \Drupal\update\UpdateManagerInterface $updateManager
     *   Update Manager Service.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     */
    public function __construct(protected \Drupal\update\UpdateManagerInterface $updateManager, protected \Drupal\Core\Render\RendererInterface $renderer)
    {
    }

    /**
     * Returns a page about the update status of projects.
     *
     * @return array
     *   A build array with the update status of projects.
     */
    public function updateStatus(): array
    {
        $build = [
          '#theme' => 'update_report',
        ];
        if ($available = update_get_available(true)) {
            $this->moduleHandler()->loadInclude('update', 'compare.inc');
            $build['#data'] = update_calculate_project_data($available);

            // @todo Consider using 'fetch_failures' from the 'update' collection
            // in the key_value_expire service for this?
            $fetch_failed = false;
            foreach ($build['#data'] as $project) {
                if ($project['status'] === UpdateFetcherInterface::NOT_FETCHED) {
                    $fetch_failed = true;
                    break;
                }
            }
            if ($fetch_failed) {
                $message = ['#theme' => 'update_fetch_error_message'];
                $this->messenger()->addError($this->renderer->renderInIsolation($message));
            }
        }
        return $build;
    }

    /**
     * Manually checks the update status without the use of cron.
     */
    public function updateStatusManually()
    {
        $this->updateManager->refreshUpdateData();
        $batch_builder = (new BatchBuilder())
          ->setTitle($this->t('Checking available update data'))
          ->addOperation($this->updateManager->fetchDataBatch(...), [])
          ->setProgressMessage($this->t('Trying to check available update data ...'))
          ->setErrorMessage($this->t('Error checking available update data.'))
          ->setFinishCallback('update_fetch_data_finished');
        batch_set($batch_builder->toArray());
        return batch_process('admin/reports/updates');
    }

}
