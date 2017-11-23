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
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    if ($user) {
      $api_key = $user->get('field_api_key')->value;
    } else {
      $api_key = false;
    }

    $response = arborelastic_search($path_id, $query, $_GET);

    $api_url = \Drupal::config('arborcat.settings')->get('api_url');

    // Get material types from API
    $guzzle = \Drupal::httpClient();
    $mat_types = $guzzle->get("http://$api_url/mat-names")->getBody()->getContents();
    $mat_names = json_decode($mat_types);
    $response['mat_names'] = $mat_names;

    $block_manager = \Drupal::service('plugin.manager.block');
    $facets = $block_manager->createInstance('catalog_facets_block')->build();

    // build the pager
    $page = pager_find_page();
    $size = (isset($_GET['size']) ? $_GET['size'] : 25);
    $pager = pager_default_initialize($response['hits']['total'], $size);

    return [
      [
        '#title' => 'Search',
        '#theme' => 'search_results',
        '#api_key' => $api_key,
        '#results' => $response,
        '#facets' => $facets
      ],
      [
        '#type' => 'pager',
        '#quantity' => 3
      ]
    ];
  }

}
