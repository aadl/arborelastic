<?php /**
 * @file
 * Contains \Drupal\arborcat\Controller\DefaultController.
 */

namespace Drupal\arborelastic\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the weed module.
 */
class DefaultController extends ControllerBase {

  public function index($path_id, $query) {
    $output = "<h1>Search Results for $path_id, $query</h1>";

    $response = arborelastic_search($path_id, $query, $_GET);

    $output .= '<p>Search returned ' . $response['hits']['total'] . ' total hits</p>';

    foreach ($response['hits']['hits'] as $hit) {
      // Theme according to $hit['_type']
      $output .= '<pre>' . print_r($hit, 1) . '</pre>';
    }

    return array(
      '#title' => 'Search',
      '#markup' => $output,
    );
  }

}
