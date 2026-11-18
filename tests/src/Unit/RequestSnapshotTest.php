<?php

namespace Drupal\Tests\arborelastic\Unit;

use Drupal\arborelastic\Search\ArborElasticQuery;
use Drupal\arborelastic\Search\IndexProfile\CatalogIndexProfile;
use Drupal\arborelastic\Search\IndexProfile\CommunityIndexProfile;
use Drupal\arborelastic\Search\IndexProfile\WebsiteIndexProfile;
use Drupal\arborelastic\Search\Debug\QueryNamer;
use Drupal\Tests\UnitTestCase;

/*
  Compares the request built for each search in fixtures/requests.json with the one recorded there,
  so structural changes can be shown to leave requests byte-identical. No Drupal config or
  Elasticsearch is needed: the profile is constructed directly and nothing is sent.

    vendor/bin/phpunit -c web/core web/modules/custom/arborelastic/tests
*/
class RequestSnapshotTest extends UnitTestCase
{
  private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

  /**
   * @dataProvider requestProvider
   */
  public function testRequestMatchesFixture(string $pathId, string $query, array $args, string $expected): void
  {
    $index = json_decode($expected, true)['index'];
    $namer = new QueryNamer(!empty($args['named_queries']));
    $profile = match ($pathId) {
      'catalog' => new CatalogIndexProfile($index, $namer),
      'website' => new WebsiteIndexProfile($index, $namer),
      'community' => new CommunityIndexProfile($index, $namer),
    };
    $actual = (new ArborElasticQuery($pathId, $query, $args, $profile))->buildRequest();

    $this->assertSame($expected, json_encode($actual, self::JSON_FLAGS));
  }

  public static function requestProvider(): array
  {
    $raw = file_get_contents(__DIR__ . '/../../fixtures/requests.json');
    $cases = json_decode($raw, true);
    // decoded again as objects so empty objects ({}) and empty arrays ([]) re-encode distinctly
    $requests = json_decode($raw);

    $data = [];
    foreach ($cases as $i => $case) {
      $label = sprintf('#%d %s: %s %s', $i, $case['path_id'], $case['query'], $case['args'] ? json_encode($case['args']) : '');
      $data[$label] = [$case['path_id'], $case['query'], $case['args'], json_encode($requests[$i]->request, self::JSON_FLAGS)];
    }
    return $data;
  }
}
