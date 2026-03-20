<?php

declare (strict_types=1);
/**
 * @file
 * The PHP page that handles updating the Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */
use Drupal\Core\Update\Update_Kernel;
use Symfony\Component\Http_Foundation\Request;
$autoloader = require_once 'autoload.php';
// Disable garbage collection during test runs. Under certain circumstances the
// update path will create so many objects that garbage collection causes
// segmentation faults.
if (drupal_valid_test_ua()) {
    gc_collect_cycles();
    gc_disable();
}
$kernel = new Update_Kernel('prod', $autoloader, false);
$request = Request::create_from_globals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);