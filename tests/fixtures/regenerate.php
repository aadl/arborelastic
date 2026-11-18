<?php

/*
  Rewrites requests.json with the request structure the current code builds for each case in within the json file. Run it
  only when a change is meant to alter requests, and review the fixture diff alongside the change:

    drush php:script modules/custom/arborelastic/tests/fixtures/regenerate.php

  To add a case, append {"path_id", "query", "args"} to requests.json and run this. Existing cases
  keep the index name they were recorded with, so local index config doesn't leak into the fixtures;
  new cases take it from config.
*/

use Drupal\arborelastic\Search\ArborElasticQuery;
use Drupal\arborelastic\Search\IndexProfile\CatalogIndexProfile;
use Drupal\arborelastic\Search\IndexProfile\CommunityIndexProfile;
use Drupal\arborelastic\Search\IndexProfile\WebsiteIndexProfile;
use Drupal\arborelastic\Search\Debug\QueryNamer;

$file = __DIR__ . '/requests.json';
$cases = json_decode(file_get_contents($file), true);
foreach ($cases as &$case) {
  $profile = null;
  if (isset($case['request']['index'])) {
    $namer = new QueryNamer(!empty($case['args']['named_queries']));
    $profile = match ($case['path_id']) {
      'catalog' => new CatalogIndexProfile($case['request']['index'], $namer),
      'website' => new WebsiteIndexProfile($case['request']['index'], $namer),
      'community' => new CommunityIndexProfile($case['request']['index'], $namer),
    };
  }
  $case['request'] = (new ArborElasticQuery($case['path_id'], $case['query'], $case['args'], $profile))->buildRequest();
}
file_put_contents($file, json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($cases) . " requests written to $file\n";
