<?php

namespace Drupal\arborelastic\Search\Score;

use Drupal\arborelastic\Search\Query\AnalyzedQuery;

/*
  Translates a query boost's config (from IndexProfile::getQueryBoosts()). Boosts multiply
  alongside the profile's base functions (popularity etc.), and return null
  when the search text doesn't need them
 */

class QueryBoostBuilder
{
  public function build(array $boost, AnalyzedQuery $q): ?array
  {
    switch ($boost['type']) {
      /*
        Multiplies records where any number in the query appears in the given fields, in digit,
        ordinal or written form ("4", "4th", "four", "fourth"). Season/volume numbers are written
        inconsistently across title-like fields, and a single number cannot overcome popularity weight
        by itself. This largely fixes the problem of "season" poisoning a search result
      */
      case 'number_match':
        if (count($q->numbers) === 0) {
          return null;
        }
        return [
          'filter' => [
            'multi_match' => [
              'query' => implode(' ', array_merge(...array_values($q->numbers))),
              'fields' => $boost['fields'],
            ],
          ],
          'weight' => $boost['weight'],
        ];
    }

    // types are only ever set in profile code, so an unknown one is a typo there
    throw new \LogicException("Unknown query boost type '{$boost['type']}'");
  }
}
