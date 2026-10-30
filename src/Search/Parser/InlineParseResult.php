<?php

namespace Drupal\arborelastic\Search\Parser;

/*
  Segmented "field:value" terms extracted out of a raw search string.
*/

class InlineParseResult
{
  public function __construct(
    // the search with standalone field prefixes (author:) removed, used if the search isn't handled as inline
    public readonly string $query,
    // Segment[]: one per recognized inline field, in the order typed
    public readonly array $segments,
    // free text typed before the first field
    public readonly string $remainder,
    // whether a standalone field (e.g. author) was present
    public readonly bool $hasStandalone,
  ) {}
}
