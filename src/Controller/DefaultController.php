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
    $response = arborelastic_search($path_id, $query, $_GET);

    return [
      '#title' => 'Search',
      '#theme' => 'catalog_results',
      '#results' => $response
    ];
  }

}
