<?php

namespace Drupal\arborelastic\Search\IndexProfile;

use Drupal\arborelastic\Search\Debug\QueryNamer;

class IndexProfileFactory
{
  public function create(string $pathId, QueryNamer $namer = new QueryNamer()): IndexProfileInterface
  {
    $indexName = $this->resolveIndexName($pathId);

    return match ($pathId) {
      'catalog' => new CatalogIndexProfile($indexName, $namer),
      'website' => new WebsiteIndexProfile($indexName, $namer),
      'community' => new CommunityIndexProfile($indexName, $namer),
      default => throw new \InvalidArgumentException("Unknown search path_id: $pathId"),
    };
  }

  private function resolveIndexName(string $pathId): string
  {
    $indexes = \Drupal::config('arborelastic.settings')->get('arborelastic_indexes');
    $indexes = explode("\n", $indexes);
    $map = [];
    foreach ($indexes as $index) {
      $parts = explode(':', $index);
      $map[$parts[0]] = trim($parts[1]);
    }
    return $map[$pathId] ?? '';
  }
}
