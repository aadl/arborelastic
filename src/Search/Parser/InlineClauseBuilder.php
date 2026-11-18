<?php

namespace Drupal\arborelastic\Search\Parser;

/*
  The must/must_not clauses for a parsed inline query.
  Standalone inline fields (the profile's name fields, e.g. author) are handled by a separate clause

  With a standalone field present, operators bind NOT first, then AND, then OR, so "a AND b OR c" means
  (a AND b) OR c. Without one, the query is left to query_string, which reads c as optional.
  This is to preserve broader overdrive matching
 */

class InlineClauseBuilder
{
  /**
   * @return array{must: array, must_not: array, inline: bool}
   *   inline is false when the query should continue to the non-inline
   *   handling (no inline fields, or standalone-only queries joined by AND).
   */
  public function build(InlineParseResult $parsed): array
  {
    $segments = $parsed->segments;
    $result = ['must' => [], 'must_not' => [], 'inline' => false];
    if (count($segments) === 0) {
      return $result;
    }

    if (!$this->hasPositive($parsed)) {
      // guard against poor NOT queries that create confusing results. Queries need some positives to return
      $result['must'][] = ['match_none' => (object) []];
      $result['inline'] = true;
      return $result;
    }

    $hasStandalone = count(array_filter($segments, fn($s) => $s->standalone)) > 0;
    $hasField = count(array_filter($segments, fn($s) => !$s->standalone)) > 0;

    // standalone-only queries (e.g. author:) joined by AND keep matching the remaining query text through
    // the regular (non-inline) query handling, which provides their relevance ranking
    if (!$hasField && !$this->hasNegation($segments) && !$this->hasOr($segments)) {
      foreach ($segments as $segment) {
        array_push($result['must'], ...$segment->extras);
      }
      return $result;
    }

    $result['inline'] = true;
    $groups = $hasStandalone ? $this->splitOnOr($segments) : [$segments];
    $built = [];
    foreach ($groups as $i => $group) {
      $built[] = $this->buildGroup($group, $i === 0 ? $parsed->remainder : '');
    }

    if (count($built) === 1) {
      $result['must'] = $built[0]['must'];
      $result['must_not'] = $built[0]['must_not'];
    } else {
      $should = [];
      foreach ($built as $group) {
        $should[] = ['bool' => array_filter(['must' => $group['must'], 'must_not' => $group['must_not']])];
      }
      $result['must'][] = ['bool' => ['should' => $should, 'minimum_should_match' => 1]];
    }

    return $result;
  }

  /*
    A group's clauses: extras (lenient name matches, exact-match enforcement) for
    positive segments, then one query_string for its other fields. Negated standalone
    fields go to must_not. Negated fields are negated inside the query_string, and their extras
    are dropped since the extras only narrow what the field already matches.
  */
  private function buildGroup(array $group, string $remainder): array
  {
    $must = [];
    $mustNot = [];
    foreach ($group as $segment) {
      if ($segment->standalone && $segment->not) {
        array_push($mustNot, ...$segment->extras);
      } elseif (!$segment->not) {
        array_push($must, ...$segment->extras);
      }
    }

    $queryString = $this->renderQueryString($group);
    if ($queryString !== '' || $remainder != null) {
      $must[] = [
        'query_string' => [
          "query" => $remainder != null ? '*:' . $this->remainderPrefix($remainder) . $queryString : $queryString,
          "default_operator" => "and",
          "fuzzy_prefix_length" => 3,
          "fuzziness" => 1,
        ],
      ];
    }

    return ['must' => $must, 'must_not' => $mustNot];
  }

  /*
    Joins a group's query_string fields with the operators typed between them. Between
    adjacent fields the typed operator is used; across a standalone field, an AND typed after the
    preceding field is kept, since standalone fields are matched outside the query_string.
  */
  private function renderQueryString(array $group): string
  {
    $queryString = '';
    $lastField = null;
    foreach ($group as $i => $segment) {
      if ($segment->standalone) {
        continue;
      }
      $previous = $group[$i - 1] ?? null;
      if ($queryString === '') {
        $separator = $segment->not ? 'NOT ' : '';
      } elseif ($previous !== null && !$previous->standalone) {
        $separator = $previous->operator === null ? '' : ' ' . $previous->operator . ' ';
      } elseif ($segment->not) {
        $separator = ' NOT ';
      } else {
        $separator = $lastField->operator === 'AND' ? ' AND ' : '';
      }
      $queryString .= $separator . $segment->queryable;
      $lastField = $segment;
    }
    return $queryString;
  }

  /*
    Free text left over after the inline fields precedes the query_string. Colons are escaped since
    any left in it belong to text that wasn't a recognized field (e.g. "Title:dune" or authr:vonnegut), and it is kept
    separated from the first field.
  */
  private function remainderPrefix(string $remainder): string
  {
    $remainder = str_replace(':', '\\:', $remainder);
    return preg_match('/\s$/', $remainder) ? $remainder : $remainder . ' ';
  }

  /*
    AND is tighter than OR: each OR starts a new group of AND-joined segments. OR NOT
    excludes like NOT rather than adding a group of "everything but", matching default
    query_string behavior for its own fields.
  */
  private function splitOnOr(array $segments): array
  {
    $groups = [[]];
    foreach ($segments as $i => $segment) {
      if ($i > 0 && $segments[$i - 1]->operator === 'OR') {
        $groups[] = [];
      }
      $groups[count($groups) - 1][] = $segment;
    }
    return $groups;
  }

  private function hasPositive(InlineParseResult $parsed): bool
  {
    if (trim($parsed->remainder) !== '') {
      return true;
    }
    return count(array_filter($parsed->segments, fn($s) => !$s->not)) > 0;
  }

  private function hasNegation(array $segments): bool
  {
    return count(array_filter($segments, fn($s) => $s->not)) > 0;
  }

  private function hasOr(array $segments): bool
  {
    return count(array_filter($segments, fn($s) => $s->operator === 'OR')) > 0;
  }
}
