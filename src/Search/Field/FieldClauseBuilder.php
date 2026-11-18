<?php

namespace Drupal\arborelastic\Search\Field;

/*
  Converts a matchable field's config (from IndexProfile::getMatchableFields())
  plus a key/value pair into a query clause.

  prefix, simple and custom are available but not currently used by any profile.
 */

class FieldClauseBuilder
{
  public function build(array $matchable, string $key, $value): ?array
  {
    if (isset($matchable['prepend'])) {
      $key = $matchable['prepend'] . '.' . $key;
    }
    if (isset($matchable['append'])) {
      $key = $key . '.' . $matchable['append'];
    }
    // mat_code bypasses .keyword, because the subfield is not configured on the website/local history index due to how elasticsearch_connector creates the index mapping.
    switch ($matchable['type']) {
      case 'date_range':
        $dates = explode(',', $value);
        return [
          'range' => [
            $key => [
              'gte' => $dates[0],
              'lte' => $dates[1],
            ],
          ],
        ];

      case 'lexile_range':
        $ceiling = 100 * ($value / 100 + 1) - 1;
        return [
          'range' => [
            $key => [
              'gte' => $value,
              'lte' => $ceiling,
            ],
          ],
        ];

      case 'match':
        return [
          'match' => [
            $key => $value,
          ],
        ];

      case 'prefix':
        return [
          'prefix' => [
            $key => [
              'value' => $value,
            ],
          ],
        ];

      case 'simple':
        return [
          'simple_query_string' => [
            'query' => $value,
            'fields' => [$key],
          ],
        ];

      case 'match_phrase':
        return [
          'match_phrase' => [
            $key => [
              'query' => $value,
            ],
          ],
        ];

      case 'term':
        return [
          'term' => [
            $key . (($matchable['keyword'] ?? true) ? '.keyword' : '') => [
              'value' => $value,
            ],
          ],
        ];

      case 'terms':
        return [
          'terms' => [
            $key . (($matchable['keyword'] ?? true) ? '.keyword' : '') => explode(',', $value),
          ],
        ];

      case 'custom':
        return ($matchable['build'])($key, $value);
    }

    // types are only ever set in profile code, so an unknown one is a typo there
    throw new \LogicException("Unknown matchable field type '{$matchable['type']}' for '$key'");
  }
}
