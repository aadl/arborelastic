<?php

namespace Drupal\arborelastic\Search\IndexProfile;

use Drupal\arborelastic\Search\Debug\QueryNamer;

/*
  Shared defaults for the two simple indices (website, community). Catalog
  overrides nearly all of this since its relevancy logic is genuinely more
  involved.
*/
abstract class AbstractIndexProfile implements IndexProfileInterface
{
  public function __construct(protected string $indexName, protected QueryNamer $namer = new QueryNamer())
  {
  }

  public function getIndexName(): string
  {
    return $this->indexName;
  }

  public function getBaseQuery(int $from, int $size): array
  {
    return [
      'index' => $this->indexName,
      'from' => $from,
      'body' => [
        'size' => $size,
        'query' => [
          'function_score' => [
            'query' => [
              'bool' => [],
            ],
          ],
        ],
      ],
    ];
  }

  public function getDefaultSorts(): array
  {
    return [];
  }

  public function getDirectSortFields(): array
  {
    return ['bib_created'];
  }

  // the node indices have no loosely analyzed subfields, so a quoted phrase is exact on every field
  public function getQuotedFields(): ?array
  {
    return null;
  }

  public function getExactPhraseBoost(): array
  {
    return [
      'fields' => ['title'],
      'boost' => 500,
    ];
  }

  public function getOperatorQuery(string $query): ?array
  {
    return null;
  }

  public function getQueryBoosts(): array
  {
    return [];
  }

  public function getFallbackQuery(string $query): array
  {
    return [
      'query_string' => [
        'query' => $query,
        'type' => 'cross_fields',
        'fields' => ['*'],
        'default_operator' => 'and',
        'fuzzy_prefix_length' => 2,
        'fuzziness' => 'AUTO',
      ],
    ];
  }
}
