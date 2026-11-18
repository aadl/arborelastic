<?php

namespace Drupal\arborelastic\Search\Query;

/*
  Thin wrapper around the raw Elasticsearch request array so callers don't
  have to repeat the deeply nested body.query.function_score.query.bool.*
  path every time they add a clause. This is mostly for readability, but it 
  relies on pretty much everything living within a top level function score
 */

class EsQueryDocument
{
  public function __construct(private array $query) {}

  public function addMust(array $clause): void
  {
    $this->query['body']['query']['function_score']['query']['bool']['must'][] = $clause;
  }

  public function addMustNot(array $clause): void
  {
    $this->query['body']['query']['function_score']['query']['bool']['must_not'][] = $clause;
  }

  public function addFilter(array $clause): void
  {
    $this->query['body']['query']['function_score']['query']['bool']['filter'][] = $clause;
  }

  public function addShould(array $clause): void
  {
    $this->query['body']['query']['function_score']['query']['bool']['should'][] = $clause;
  }

  public function addFunction(array $function): void
  {
    $this->query['body']['query']['function_score']['functions'][] = $function;
  }

  public function setSort(array $sort): void
  {
    $this->query['body']['sort'] = $sort;
  }

  public function toArray(): array
  {
    return $this->query;
  }
}
