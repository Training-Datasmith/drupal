<?php

declare (strict_types=1);
/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */
use Drupal\Core\Drupal_Kernel;
use Symfony\Component\Http_Foundation\Request;
$autoloader = require_once 'autoload.php';
$kernel = new Drupal_Kernel('prod', $autoloader);
$request = Request::create_from_globals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);