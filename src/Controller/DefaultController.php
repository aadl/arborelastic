<?php

/**
 * @file
 * Contains \Drupal\arborcat\Controller\DefaultController.
 */

namespace Drupal\arborelastic\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the weed module.
 */
class DefaultController extends ControllerBase
{

  public function index($path_id, $query)
  {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    if ($user) {
      $api_key = $user->get('field_api_key')->value;
      $lists = arborcat_lists_get_lists($user->get('uid')->value);
    } else {
      $api_key = false;
      $lists = false;
    }
    $response = arborelastic_search($path_id, $query, $_GET);
    $gridview = (isset($_GET['gridview']) && $_GET['gridview'] == 'true' ? true : false);

    $api_url = \Drupal::config('arborcat.settings')->get('api_url');

    // Get material types from API
    $guzzle = \Drupal::httpClient();
    $mat_types = $guzzle->get("$api_url/mat-names")->getBody()->getContents();
    $mat_names = json_decode($mat_types);
    $response['mat_names'] = $mat_names;
    $block_manager = \Drupal::service('plugin.manager.block');
    $facets = $block_manager->createInstance('catalog_facets_block', ['type' => $path_id])->build();
    // build the pager
    $pager_manager = \Drupal::service('pager.manager');
    $page = \Drupal::service('pager.parameters')->findPage();
    $size = (isset($_GET['size']) ? $_GET['size'] : 25);

    $pager = \Drupal::service('pager.manager')->createPager($response['hits']['total']['value'], $size);

    return [
      [
        '#title' => 'Search',
        '#theme' => 'search_results',
        '#path_id' => $path_id,
        '#api_key' => $api_key,
        '#lists' => $lists,
        '#results' => $response,
        '#facets' => $facets,
        '#gridview' => $gridview,
        '#cache' => ['max-age' => 0],
        '#pager' => [
          '#type' => 'pager',
          '#quantity' => 5
        ]
      ]
    ];
  }
}
