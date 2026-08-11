<?php

namespace Drupal\arborelastic\Search;

use Drupal\arborelastic\Search\Connection\ElasticsearchConnectionFactory;
use Drupal\arborelastic\Search\Debug\MatchedQueryLogger;
use Drupal\arborelastic\Search\Debug\QueryNamer;
use Drupal\arborelastic\Search\Field\FieldClauseBuilder;
use Drupal\arborelastic\Search\IndexProfile\IndexProfileFactory;
use Drupal\arborelastic\Search\IndexProfile\IndexProfileInterface;
use Drupal\arborelastic\Search\Parser\InlineClauseBuilder;
use Drupal\arborelastic\Search\Parser\InlineFieldQueryParser;
use Drupal\arborelastic\Search\Query\AnalyzedQuery;
use Drupal\arborelastic\Search\Query\EsQueryDocument;
use Drupal\arborelastic\Search\Query\QueryPlan;
use Drupal\arborelastic\Search\Score\QueryBoostBuilder;
use Elasticsearch\Client;

class ArborElasticQuery
{
  public $args;
  public $query;
  public $path_id;
  private ?Client $connection;
  private IndexProfileInterface $profile;
  private QueryNamer $namer;
  private EsQueryDocument $doc;
  private FieldClauseBuilder $fieldClauseBuilder;
  private InlineFieldQueryParser $inlineParser;
  private InlineClauseBuilder $inlineClauseBuilder;
  private QueryBoostBuilder $queryBoostBuilder;

  /*
    $profile and $connection are optional so tests can build requests without Drupal config or a
    live cluster; by default the profile comes from IndexProfileFactory and the client is created
    on first search. The client and profile factory are candidates for Drupal services if the
    team prefers that pattern.
  */
  public function __construct($path_id, $query, $args, ?IndexProfileInterface $profile = null, ?Client $connection = null)
  {
    $this->args = $args;
    $this->query = $query;
    $this->path_id = $path_id;

    $this->namer = new QueryNamer(!empty($args['named_queries']));
    $this->profile = $profile ?? (new IndexProfileFactory())->create($path_id, $this->namer);

    $this->fieldClauseBuilder = new FieldClauseBuilder();
    $this->inlineParser = new InlineFieldQueryParser();
    $this->inlineClauseBuilder = new InlineClauseBuilder();
    $this->queryBoostBuilder = new QueryBoostBuilder();
    $this->connection = $connection;
  }

  /*
    The Elasticsearch request for this search, without sending it. Each call starts again from
    the profile's base query.
  */
  public function buildRequest(): array
  {
    $page = $this->args['page'] ?? 0;
    $size = $this->args['size'] ?? 25;
    $this->doc = new EsQueryDocument($this->profile->getBaseQuery($page * $size, $size));

    $this->applyFilterTerms();
    $plan = $this->applyQueryTerms();
    $this->applyMatchTerms();
    $this->applySortTerms();
    $this->applyFlatBoosts($plan);
    $this->applyQueryBoosts($plan);

    return $this->doc->toArray();
  }

  public function query(): array
  {
    $request = $this->buildRequest();

    try {
      $this->connection ??= (new ElasticsearchConnectionFactory())->create();
      $result = $this->connection->search($request);
    } catch (\Exception $e) {
      $result = [
        'error' => 'Elasticsearch Query Failed',
        'message' => $e->getMessage(),
      ];
    }
    $result['cur_page'] = $this->args['page'] ?? 0;
    $result['size'] = $this->args['size'] ?? 25;

    if (!empty($this->args['named_queries']) && isset($result['hits']['hits'])) {
      (new MatchedQueryLogger())->log($this->path_id, $this->query, $request['from'], $result['hits']['hits']);
    }

    return $result;
  }

  private function applyQueryTerms(): QueryPlan
  {
    $parsed = $this->inlineParser->parse($this->query, $this->profile->getSearchableFields());
    $text = $parsed->query;
    $inlineClauses = $this->inlineClauseBuilder->build($parsed);
    foreach ($inlineClauses['must'] as $clause) {
      $this->doc->addMust($clause);
    }
    foreach ($inlineClauses['must_not'] as $clause) {
      $this->doc->addMustNot($clause);
    }
    if ($inlineClauses['inline']) {
      return new QueryPlan($text, inline: true);
    }

    // if there are no set terms, check for quotation search and set query_string with string escapes
    if (preg_match('/^"(.*?)"/', $text)) {
      if (!$parsed->hasStandalone) {
        $quoted = [
          "query" => (new AnalyzedQuery($text))->escaped,
          "default_operator" => "and",
        ];
        $analyzer = $this->profile->getQuotedAnalyzer();
        if ($analyzer !== null) {
          $quoted['analyzer'] = $analyzer;
        }
        $this->doc->addMust(['query_string' => $quoted]);
      }
      return new QueryPlan($text);
    }
    if ($text === '*') {
      $this->doc->addMust([
        'query_string' => [
          "query" => $text,
          "default_operator" => "and",
          "fuzzy_prefix_length" => 3,
          "fuzziness" => 1,
        ],
      ]);
      return new QueryPlan($text);
    }
    if ($operatorClause = $this->profile->getOperatorQuery($text)) {
      $this->doc->addMust($operatorClause);
      return new QueryPlan($text);
    }
    $this->doc->addMust($this->profile->getFallbackQuery($text));
    return new QueryPlan($text);
  }

  private function applyMatchTerms(): void
  {
    $matchables = $this->profile->getMatchableFields();
    foreach ($this->args as $k => $v) {
      if (!array_key_exists($k, $matchables)) {
        continue;
      }
      $clause = $this->fieldClauseBuilder->build($matchables[$k], $k, $v);
      if ($clause !== null) {
        $this->doc->addMust($clause);
      }
    }
  }

  private function applyFilterTerms(): void
  {
    foreach ($this->profile->getDefaultFilters() as $k => $d) {
      if (isset($this->args[$k])) {
        continue;
      }
      $this->doc->addFilter($d['type'] === 'raw' ? $d['value'] : [
        $d['type'] => [
          $k => $d['value'],
        ],
      ]);
    }
  }

  private function applySortTerms(): void
  {
    $direct = array_merge(['_score'], $this->profile->getDirectSortFields());
    if (isset($this->args['sort'])) {
      $sort_parts = explode('~', $this->args['sort']);
      $sort_field = $sort_parts[0];
      $order = $sort_parts[1] ?? 'desc'; // Default sort decending
      $field = in_array($sort_field, $direct) ? $sort_field : $sort_field . '.keyword';
      $this->doc->setSort([$field => ['order' => $order]]);
    } elseif (isset($this->args['mat_code'])) {
      $default = $this->profile->getDefaultSorts()[$this->args['mat_code']] ?? null;
      if ($default !== null) {
        $this->doc->setSort($default);
      }
    }
  }

  // Helps boost closer exact matches over partial matches in broader queries
  private function applyFlatBoosts(QueryPlan $plan): void
  {
    if (!$plan->allowsRelevanceBoosts()) {
      return;
    }
    $exactPhrase = $this->profile->getExactPhraseBoost();
    $this->doc->addShould($this->namer->named('exact_phrase', [
      'query_string' => [
        "query" => '"' . $plan->text . '"',
        "fields" => $exactPhrase['fields'],
        "boost" => $exactPhrase['boost'],
      ],
    ]));
  }

  private function applyQueryBoosts(QueryPlan $plan): void
  {
    if (!$plan->allowsRelevanceBoosts()) {
      return;
    }
    $boosts = $this->profile->getQueryBoosts();
    if (count($boosts) === 0) {
      return;
    }
    $analyzed = new AnalyzedQuery($plan->text);
    foreach ($boosts as $boost) {
      $function = $this->queryBoostBuilder->build($boost, $analyzed);
      if ($function !== null) {
        $this->doc->addFunction($function);
      }
    }
  }
}
