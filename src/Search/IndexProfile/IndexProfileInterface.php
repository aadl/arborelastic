<?php

namespace Drupal\arborelastic\Search\IndexProfile;

/*
  Retrieval for different facets between indices (local history, bibs, website).
  Includes query scaffold, which fields are searchable/matchable/filterable/sortable
  by default, and the relevancy tuning used when a raw query string.
 */

interface IndexProfileInterface
{
  public function getPathId(): string;

  public function getIndexName(): string;

  public function getBaseQuery(int $from, int $size): array;

  public function getSearchableFields(): array;

  public function getMatchableFields(): array;

  public function getDefaultFilters(): array;

  public function getDefaultSorts(): array;

  // date and numeric sort fields, sorted on directly rather than on their .keyword subfield
  public function getDirectSortFields(): array;

  // analyzer for quoted searches, or null to use each field's own
  public function getQuotedAnalyzer(): ?string;

  // ['fields' => [...], 'boost' => n] for the exact phrase boost on free-text searches
  public function getExactPhraseBoost(): array;

  public function getOperatorQuery(string $query): ?array;

  public function getFallbackQuery(string $query): array;

  /*
    Boosts triggered by the search text, keyed by name, each with a 'type'
    read by QueryBoostBuilder to build out its options. Adds
    function_score functions alongside the base query's popularity functions.
   */
  public function getQueryBoosts(): array;
}
