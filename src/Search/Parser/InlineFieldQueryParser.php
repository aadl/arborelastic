<?php

namespace Drupal\arborelastic\Search\Parser;

/*
  Parses inline "field:value" terms out of a raw search string (title:*
  author:* etc), organizing each matched field according to the owning index's
  searchableField config (name / foldable / stemmed / plain term).
 */

class InlineFieldQueryParser
{
  // boolean operator typed after an inline value, linking it to the next inline field ("chabon AND ")
  private const TRAILING_OPERATOR = '/\b(AND NOT|OR NOT|AND|OR|NOT)\s*$/';

  public function parse(string $query, array $searchableFields): InlineParseResult
  {
    $segments = [];
    $hasStandalone = false;

    // match all : separated terms and iterate over the resulting key value pairs.
    // Concatenate a query_string for as many as are supplied, checking against folded fields. store potential remainder for later wildcard inlining within stringable queries
    $remainder = str_replace('  ', ' ', $query);
    preg_match_all('/([a-z]\S*):((["\'\da-zA-Z\*\s].*?|[\*])(?=(?:[a-z]\S*:|\s\s|$)))/', $remainder, $matches);
    $keys = $matches[1];
    $values = $matches[2];
    foreach ($matches as $m) {
      $remainder = str_replace($m, '', $remainder);
    }
    preg_match('/(\sOR\s|\sAND\s)+$/', $remainder, $last);
    if ($last === null) {
      $remainder = $remainder .= ' AND ';
    }

    foreach ($keys as $i => $k) {
      if (!in_array($k, $searchableFields['fields'])) {
        continue;
      }
      // the value regex captures up to the next field, so split off any operator linking it to that field
      $value = $values[$i];
      $operator = null;
      if (preg_match(self::TRAILING_OPERATOR, $value, $op)) {
        $operator = $op[1];
        $value = substr($value, 0, -strlen($op[0]));
      }
      if (trim($value) === '') {
        continue;
      }
      $standalone = false;
      $queryable = null;
      $extras = [];

      if (in_array($k, $searchableFields['names'])) {
        // Names need broader handling for overdrive. Might be better addressed with an analyzer in the future.
        $query = str_replace($k . ':', '', $query);
        $standalone = true;
        $extras[] = $this->buildNameClause($k, $value);
        $hasStandalone = true;
      } elseif (in_array($k, $searchableFields['foldables'])) {
        $queryable = $k . '.folded:(' . trim($value) . ')';
        // enforce exact matches in searchable fields when using quotations in value & not conflicting with overdrive
        if (preg_match('/"(.*?)"/', $value)) {
          $extras[] = $this->buildExactMatchClause($k, $value);
        }
      } elseif (in_array($k, $searchableFields['stemmed'])) {
        $queryable = $k . '.stem:(' . trim($value) . ')';
      } else {
        if (in_array($k, $searchableFields['term']) && preg_match('/^"(.*?)"/', $value)) {
          $extras[] = [
            'term' => [
              $k . '.keyword' => [
                'value' => str_replace('"', '', $value),
                'case_insensitive' => true,
              ],
            ],
          ];
        }
        $queryable = $k . ':(' . trim($value) . ')';
      }
      $segments[] = new Segment($k, $standalone, $queryable, $extras, $operator);
    }

    foreach ($segments as $i => $segment) {
      if ($i > 0 && str_contains((string) $segments[$i - 1]->operator, 'NOT')) {
        $segments[$i] = $segment->negated();
      }
    }

    // a NOT typed before the first field negates it. Free text ahead of a query_string field already
    // carries its NOT into the query_string, so only a bare NOT or one leading into a standalone field is lifted out.
    if (count($segments) > 0 && preg_match('/(^|\s)NOT\s*$/', $remainder)) {
      if (trim($remainder) === 'NOT' || $segments[0]->standalone) {
        $segments[0] = $segments[0]->negated();
        $remainder = preg_replace('/(^|\s)NOT\s*$/', '$1', $remainder);
      }
    }

    return new InlineParseResult($query, $segments, $remainder, $hasStandalone);
  }

  /*
    Matches a name field and its folded variant, the latter with initials run together ("J. K." becomes
    "J.K.") so OverDrive's name formats also match.
  */
  private function buildNameClause(string $field, string $value): array
  {
    $initialsJoined = preg_replace('/(?<=\w\.)(\s)(?=\w\.)/', '', $value);
    return [
      'bool' => [
        'should' => [
          [
            'match' => [
              $field => ["query" => $value, "operator" => "and", "analyzer" => "aadl_field_analyzer"],
            ],
          ],
          [
            'match' => [
              $field . '.folded' => ["query" => $initialsJoined, "operator" => "and", "analyzer" => "aadl_field_analyzer"],
            ],
          ],
        ],
      ],
    ];
  }

  private function buildExactMatchClause(string $key, string $value): array
  {
    return [
      'query_string' => [
        'query' => $key . ':' . $value,
      ],
    ];
  }
}
