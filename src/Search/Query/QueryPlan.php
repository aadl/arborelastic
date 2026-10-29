<?php

namespace Drupal\arborelastic\Search\Query;

/*
  This is here to handle the first-pass logic on the string. Mostly just to fully detach ArborElasticQuery from the
  index profiles, so updates in the profiles can be independently without affecting another. This method affects all three

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

  /*
    The exact phrase boost wraps the text in quotes, so text with quotes of its own would become
    ""..."", which query_string reads as loose terms rather than a phrase
  */
  public function allowsExactPhraseBoost(): bool
  {
    return $this->allowsRelevanceBoosts() && !str_contains($this->text, '"');
  }
}
