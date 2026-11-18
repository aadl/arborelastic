<?php

namespace Drupal\arborelastic\Search\Debug;

/*
  Adds Elasticsearch _name labels to query clauses when named queries are enabled (named_queries=1),
  so each hit reports which clauses matched it. A clause that already contains named clauses has
  those names prefixed instead ("combined:fields"), keeping nested names traceable to their parent.
  When disabled, clauses pass through unchanged.
*/

class QueryNamer
{
  public function __construct(private bool $enabled = false) {}

  public function named(string $name, array $clause): array
  {
    if (!$this->enabled) {
      return $clause;
    }
    $namespaced = false;
    $clause = $this->namespaceNames($name, $clause, $namespaced);
    if ($namespaced) {
      return $clause;
    }
    $type = array_key_first($clause);
    if ($type === 'match' || $type === 'match_phrase') {
      $field = array_key_first($clause[$type]);
      if (!is_array($clause[$type][$field])) {
        $clause[$type][$field] = ['query' => $clause[$type][$field]];
      }
      $clause[$type][$field]['_name'] = $name;
    } else {
      $clause[$type]['_name'] = $name;
    }
    return $clause;
  }

  // names each clause by its key and returns them as a list
  public function namedClauses(array $clauses): array
  {
    $named = [];
    foreach ($clauses as $name => $clause) {
      $named[] = $this->named($name, $clause);
    }
    return $named;
  }

  private function namespaceNames(string $namespace, array $clause, bool &$namespaced): array
  {
    foreach ($clause as $key => $value) {
      if ($key === '_name') {
        $clause[$key] = $namespace . ':' . $value;
        $namespaced = true;
      } elseif (is_array($value)) {
        $clause[$key] = $this->namespaceNames($namespace, $value, $namespaced);
      }
    }
    return $clause;
  }
}
