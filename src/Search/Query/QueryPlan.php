<?php

namespace Drupal\arborelastic\Search\Query;

/*
  Outcome of applying the search text: the text the later steps work from (the raw search with
  standalone field prefixes like author: removed), and whether it was handled as inline fields.
*/

class QueryPlan
{
  public function __construct(
    public readonly string $text,
    public readonly bool $inline = false,
  ) {}

  // the exact phrase boost and query boosts apply to free-text searches, not inline fields or wildcards
  public function allowsRelevanceBoosts(): bool
  {
    return !$this->inline && !str_contains($this->text, '*');
  }
}
