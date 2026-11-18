<?php

namespace Drupal\arborelastic\Search\IndexProfile;

use Drupal\arborelastic\Search\Query\AnalyzedQuery;

class CatalogIndexProfile extends AbstractIndexProfile
{
  /*
    stripping certain stop words in php instead of elastic so we can use it against a
    combined fields query without radically increasing mapping complexity. Combined
    query returns better results than cross_fields with simpler score analysis in this
    setup.
  */
  protected const STOP_WORDS = ['a', 'an', 'the'];

  public function getPathId(): string
  {
    return 'catalog';
  }

  public function getBaseQuery(int $from, int $size): array
  {
    return [
      'index' => $this->indexName,
      'from' => $from,
      'body' => [
        'size' => $size,
        'query' => [
          'function_score' => [
            'query' => [
              'bool' => [
                'should' => [
                  [
                    'match' => [
                      'lang' => [
                        'query' => 'eng',
                        'boost' => 8,
                      ],
                    ],
                  ],
                ],
              ],
            ],
            'functions' => [
              [
                'field_value_factor' => [
                  'field' => 'popular_alltime',
                  'modifier' => 'log2p',
                  'missing' => 0,
                ],
              ],
              [
                'filter' => [
                  'term' => [
                    'bib_type' => 'overdrive',
                  ],
                ],
                'weight' => 0.1,
              ],
              [
                // 3 year relevancy very slight decay
                'exp' => [
                  'bib_created' => [
                    'origin' => 'now',
                    'scale' => '1095d',
                    'decay' => 0.9,
                  ],
                ],
                'weight' => .05,
              ],
              [
                'field_value_factor' => [
                  'field' => 'copy_count',
                  'modifier' => 'log2p',
                  'missing' => 0,
                ],
              ],
            ],
          ],
        ],
        'aggs' => [
          'mat_code' => [
            'terms' => [
              'field' => 'mat_code.keyword',
            ],
          ],
          'lang' => [
            'terms' => [
              'field' => 'lang.keyword',
            ],
          ],
        ],
      ],
    ];
  }

  public function getSearchableFields(): array
  {
    return [
      'fields' => [
        'author',
        'addl_author',
        'artist',
        'bib_created',
        'callnum',
        'callnums',
        'lang',
        'material',
        'pub_year',
        'pub_info',
        'series',
        'subjects',
        'title',
      ],
      'foldables' => ['title', 'artist'],
      'stemmed' => ['subjects'],
      'term' => ['series'],
      // matched leniently by their own clause so OverDrive's name formats also match
      'names' => ['author'],
    ];
  }

  public function getMatchableFields(): array
  {
    return [
      'available_branches' => ['type' => 'match'],
      'mat_code' => ['type' => 'terms'],
      'lang' => ['type' => 'term'],
      'nonfiction' => ['type' => 'term', 'keyword' => false],
      'ages' => ['type' => 'terms'],
      'author' => ['type' => 'match'],
      'title' => ['type' => 'match'],
      'subjects' => ['type' => 'match', 'append' => 'stem'],
      'callnum' => ['type' => 'match_phrase'],
      'callnums' => ['type' => 'match'],
      'request_only' => ['type' => 'term', 'prepend' => 'flags', 'keyword' => false],
      'reading_level' => ['type' => 'lexile_range', 'append' => 'lexile'],
      'licensed_from' => ['type' => 'match'],
    ];
  }

  public function getDefaultFilters(): array
  {
    return [
      'active' => [
        'type' => 'match',
        'value' => 1,
      ],
    ];
  }

  public function getQueryBoosts(): array
  {
    return [
      // season/volume numbers live in title_medium and callnums as often as title
      'numbers' => [
        'type' => 'number_match',
        'fields' => ['title', 'title_medium', 'callnums', 'series'],
        'weight' => 3,
      ],
    ];
  }

  public function getDirectSortFields(): array
  {
    return ['bib_created', 'popular_week', 'popular_month', 'popular_year', 'popular_alltime'];
  }

  /*
    Base fields only: the .trigram, .folded (edge-ngram) and .stem subfields would turn a quoted
    phrase into substring, prefix or stemmed matches ("harry potter's" matching every "Harry Potter" title)
  */
  public function getQuotedFields(): ?array
  {
    return ['title', 'title_medium', 'author', 'artist', 'addl_author', 'addl_title', 'series', 'subjects', 'notes', 'pub_info', 'callnum', 'callnums', 'stdnum', 'material', 'edition', 'language_note', 'license', 'licensed_from', 'pub_year', 'pub_date', 'specifications', 'tracks'];
  }

  public function getExactPhraseBoost(): array
  {
    return [
      'fields' => ['title', 'title_medium', 'author', 'artist'],
      'boost' => 500,
    ];
  }

  public function getOperatorQuery(string $query): ?array
  {
    if (strpos($query, ' AND ') || strpos($query, ' OR ') || strpos($query, '*')) {
      return $this->namer->named('operator', [
        'query_string' => [
          'query' => $query,
          'fields' => ['title.folded^20', 'author.folded^10', 'artist.folded^10', 'callnum', 'callnums', 'subjects', 'series', 'addl_author', 'addl_title', 'title_medium'],
        ],
      ]);
    }
    return null;
  }

  public function getFallbackQuery(string $query): array
  {
    $q = new AnalyzedQuery($query, self::STOP_WORDS);

    return [
      'bool' => [
        'should' => $this->namer->namedClauses([
          'broad' => $this->broadClause($q),
          'combined' => $this->combinedClause($q),
          'title_prefix' => $this->titlePrefixClause($q),
          'author_prefix' => $this->authorPrefixClause($q),
          'fuzzy' => $this->fuzzyClause($q),
        ]),
        /*
          constrain what could be bloated results from the combined_fields
          query by requiring at least two of these four clauses.
        */
        "minimum_should_match" => 2,
      ],
    ];
  }

  /*
    title and title_medium are scored as one field so words cataloged in title_medium (e.g. "Season 4")
    carry title weight instead of being discarded by the best_fields pick. Written forms of query
    numbers are included so "Season four" also matches.
  */
  protected function broadClause(AnalyzedQuery $q): array
  {
    $numberWords = [];
    foreach ($q->numbers as $forms) {
      $numberWords = array_merge($numberWords, array_slice($forms, 1));
    }

    return [
      'dis_max' => [
        'queries' => $this->namer->namedClauses([
          'title' => [
            'combined_fields' => [
              "query" => trim($q->lower . ' ' . implode(' ', $numberWords)),
              "fields" => ['title', 'title_medium'],
              "boost" => 20,
            ],
          ],
          'fields' => [
            'multi_match' => [
              "query" => $q->lower,
              "fields" => ['author.folded^10', 'artist.folded^10', 'callnum', 'callnums', 'items.barcode', 'subjects.stem', 'stdnum', 'series', 'addl_author.folded', 'addl_title', 'pub_info', 'notes'],
            ],
          ],
        ]),
      ],
    ];
  }

  /*
    Helps relevancy of multi-faceted queries (e.g. {some title} {some author}).
    If there are only 2 words, it requires all to match somewhere in the set.
    if there are more, it maxes out at 4 requiring matches among the set. Having
    notes duplicated within an additional clause allows the removal of many
    instances of common words and phrases bloating results by requiring its
    occurrence in notes to be literal if a portion is hit by combined_fields
  */
  protected function combinedClause(AnalyzedQuery $q): array
  {
    return [
      'bool' => [
        'should' => $this->namer->namedClauses([
          'fields' => [
            'combined_fields' => [
              "query" => $q->stopStripped,
              "fields" => ['title', 'author', 'artist', 'callnum', 'callnums', 'subjects', 'series', 'addl_author', 'addl_title', 'title_medium', 'notes'],
              "minimum_should_match" => "3<-10%",
            ],
          ],
          'notes' => [
            'query_string' => [
              "query" => $q->raw,
              "fields" => ['notes', 'pub_info', 'subjects.stem'],
              "analyzer" => "aadl_search_analyzer",
              "default_operator" => 'and',
            ],
          ],
        ]),
      ],
    ];
  }

  /*
    This sifts out extraneous titles composed of very short words and also boosts close/exact
    matches of short words in sequence. Also helps support multi-faceted title/author queries made
    without specified in-line fields, which could be missed by the top multi_match, but
    caught by the combined_fields.
  */
  protected function titlePrefixClause(AnalyzedQuery $q): array
  {
    return [
      'multi_match' => [
        "query" => $q->raw,
        "type" => "phrase_prefix",
        "fields" => ["title.folded^20", "addl_title", 'items.barcode', "title_medium", "series", 'stdnum'],
      ],
    ];
  }

  // same as above but for authors
  protected function authorPrefixClause(AnalyzedQuery $q): array
  {
    return [
      'multi_match' => [
        "query" => $q->raw,
        "type" => 'phrase_prefix',
        "fields" => ["author.folded^3", 'addl_author.folded'],
      ],
    ];
  }

  /*
    Use a dis_max to look for fuzzier results and use the highest score. Using a mix of tri-ngrams,
    fuzzy matches on author/title, and the same broad combined_fields query as above. For readability, the
    fuzz clause is in a separate method criticalFuzzClause, but within the same $queries array defined here in final output.
  */
  protected function fuzzyClause(AnalyzedQuery $q): array
  {
    $queries = [
      'title_trigram' => [
        'match' => [
          'title.trigram' => [
            "query" => $q->raw,
            "minimum_should_match" => "4<-10%",
            "boost" => 0.3,
          ],
        ],
      ],
      'author_trigram' => [
        'match' => [
          'author.trigram' => [
            "query" => $q->raw,
            "minimum_should_match" => "4<-25%",
            "boost" => 0.3,
          ],
        ],
      ],
    ];
    if (count($q->critical) > 0) {
      $queries['critical_fuzz'] = $this->criticalFuzzClause($q);
    }

    return [
      'dis_max' => [
        'queries' => $this->namer->namedClauses($queries),
      ],
    ];
  }

  protected function criticalFuzzClause(AnalyzedQuery $q): array
  {
    $fQuery = implode('~1 ', $q->critical) . '~1';

    return [
      'bool' => [
        'should' => [
          [
            'function_score' => [
              'query' => [
                'simple_query_string' => [
                  "fields" => ['title.folded'],
                  "query" => $fQuery,
                  "minimum_should_match" => '2<80%',
                ],
              ],
              'min_score' => 25,
            ],
          ],
          [
            'function_score' => [
              'query' => [
                'simple_query_string' => [
                  "fields" => ['author.folded'],
                  "query" => $fQuery,
                  "boost" => .5,
                ],
              ],
              'min_score' => 20,
            ],
          ],
          [
            'query_string' => [
              "query" => '"' . $q->raw . '"',
              "fields" => ['notes', 'subjects.stem'],
              "analyzer" => "aadl_search_analyzer",
              "default_operator" => 'and',
            ],
          ],
          [
            'query_string' => [
              "query" => $q->raw,
              "fields" => ['title', 'author', 'artist', 'subjects', 'series', 'addl_author', 'addl_title', 'title_medium', 'pub_info'],
              "analyzer" => "aadl_search_analyzer",
            ],
          ],
        ],
        "minimum_should_match" => 2,
      ],
    ];
  }
}
