<?php

namespace Drupal\arborelastic\Search;

use Elasticsearch\ClientBuilder;

class ArborElasticQuery
{
  public $args;
  public $query;
  public $path_id;
  private $connection;
  private $es_query;
  private $matchables;
  public function __construct($path_id, $query, $args)
  {
    $this->args = $args;
    $this->query = $query;
    $this->path_id = $path_id;
    $indexes = \Drupal::config('arborelastic.settings')->get('arborelastic_indexes');
    $indexes = explode("\n", $indexes);
    foreach ($indexes as $i => $index) {
      $parts = explode(':', $index);
      $indexes[$parts[0]] = trim($parts[1]);
      unset($indexes[$i]);
    }
    $formats = [
      'catalog' => [
        'index' => $indexes[$path_id],
        'from' => (isset($args['page']) ? $args['page'] : 0) * (isset($args['size']) ? $args['size'] : 25),
        'body' => [
          'size' => isset($args['size']) ? $args['size'] : 25,
          'query' => [
            'function_score' => [
              'query' => [
                'bool' => [
                  'should' => [
                    [
                      'match' => [
                        'lang' => [
                          'query' => 'eng',
                          'boost' => 300
                        ]
                      ]
                    ],
                  ],
                ]
              ],
              'field_value_factor' => [
                'field' => 'popular_alltime',
                'modifier' => 'log1p',
                'missing' => 1
              ]
            ]
          ],
          'aggs' => [
            'mat_code' => [
              'terms' => [
                'field' => 'mat_code.keyword'
              ]
            ],
            'lang' => [
              'terms' => [
                'field' => 'lang.keyword'
              ]
            ]
          ],
        ]
      ],
      'website' => [
        'index' => $indexes[$path_id],
        'from' => (isset($args['page']) ? $args['page'] : 0) * (isset($args['size']) ? $args['size'] : 25),
        'body' => [
          'size' => isset($args['size']) ? $args['size'] : 25,
          'query' => [
            'function_score' => [
              'query' => [
                'bool' => []
              ]
            ]
          ],

        ]
      ],
      'community' => [
        'index' => $indexes[$path_id],
        'from' => (isset($args['page']) ? $args['page'] : 0) * (isset($args['size']) ? $args['size'] : 25),
        'body' => [
          'size' => isset($args['size']) ? $args['size'] : 25,
          'query' => [
            'function_score' => [
              'query' => [
                'bool' => []
              ]
            ]
          ],
        ]
      ]
    ];
    // prepend and appends are for nested fields. May want to add a dedicated method at some point to flesh these out
    $matchables = [
      'catalog' => [
        'available_branches' => ['type' => 'match'],
        'mat_code' => ['type' => 'terms'],
        'lang' => ['type' => 'term'],
        'nonfiction' => ['type' => 'term', 'keyword' => false],
        'ages' => ['type' => 'terms'],
        'author' => ['type' => 'match'],
        'title' => ['type' => 'match'],
        'subjects' => ['type' => 'match'],
        'callnum' => ['type' => 'match_phrase'],
        'callnums' => ['type' => 'match'],
        'request_only' => ['type' => 'term', 'prepend' => 'flags', 'keyword' => false],
        'reading_level' => ['type' => 'lexile_range', 'append' => 'lexile'],
        'licensed_from' => ['type' => 'match']
      ],
      'community' => [
        'old_news_taxonomy' => ['type' => 'terms'],
        'mat_code' => ['type' => 'terms', 'keyword' => false],
        'oldnews_date' => [
          'type' => 'date_range',
        ],
        'photo_indexed' => [
          'type' => 'term'
        ]
      ],
      'website' => [
        'mat_code' => ['type' => 'terms', 'keyword' => false],
        'location_name' => ['type' => 'term'],
        'event_ages' => ['type' => 'terms'],
        'event_categories' => ['type' => 'terms'],
      ]
    ];
    $this->matchables = $matchables[$path_id];
    $this->es_query = $formats[$path_id];
    $hosts = [
      \Drupal::config('arborelastic.settings')->get('arborelastic_host') . ':' .  \Drupal::config('arborelastic.settings')->get('arborelastic_port'),
    ];
    $this->connection = ClientBuilder::create()->setHosts($hosts)->build();
  }
  public function query()
  {
    $this->applyFilterTerms();
    $this->applyQueryTerms();
    $this->applyMatchTerms();
    $this->applySortTerms();
    $this->applyFlatBoosts();

    try {
      $result = $this->connection->search($this->es_query);
    } catch (\Exception $e) {
      $result = [
        'error' => 'Elasticsearch Query Failed',
        'message' => $e->getMessage(),
      ];
    }
    $result['cur_page'] = $this->args['page'] ?? 0;
    $result['size'] = $this->args['size'] ?? 25;

    return $result;
  }
  private function applyQueryTerms()
  {
    // Queryables in this function are inline search terms. title:* author:* etc
    $search_fields = [
      'catalog' => [
        'fields' => [
          'author',
          'artist',
          'bib_created',
          'callnum',
          'callnums',
          'lang',
          'pub_year',
          'pub_info',
          'series',
          'subjects',
          'title',
        ],
        'foldables' => [
          'title',
          'author',
          'artist'
        ]
      ],
      'community' => [
        'fields' => [
          'title',
          'photographer',
          'authored_by',
        ],
        'foldables' => []
      ],
      'website' => [
        'fields' => [
          'title',
          'location_name'
        ],
        'foldables' => []
      ]
    ];
    $queryables = [];

    // match all : separated terms and iterate over the resulting key value pairs. Concatenate a query_string for as many as are supplied, checking against folded fields
    preg_match_all('/([a-z]\S*):(["\'\da-z\s].+?(?=(?:[a-z]\S*:|$)))/', $this->query, $matches);
    $keys = $matches[1];
    $values = $matches[2];
    // place terms into array to then iterate over again to check for operators. Doing this separately is less efficient but easier to handle.
    foreach ($keys as $i => $k) {
      if (in_array($k, $search_fields[$this->path_id]['fields'])) {
        if (in_array($k, $search_fields[$this->path_id]['foldables'])) {
          // Authors need broader handling for overdrive. Might be better addressed with an analyzer in the future.
          if ($k === 'author') {
            $this->handleOverdrive($values[$i]);
          } else {
            $queryables[] = $k . '.folded:(' . trim($values[$i]) . ')';
            // enforce exact matches in searchable fields when using quotations in value & not conflicting with overdrive
            if (preg_match('/"(.*?)"/', $values[$i])) {
              $this->enforceExactMatches($k, $values[$i]);
            }
          }
        } else {
          $queryables[] = $k . ':(' . trim($values[$i]) . ')';
        }
      }
    }

    foreach ($queryables as $i => $q) {
      if (strpos($q, ' OR)')) {
        $queryables[$i] = str_replace(' OR)', ')', $q) . ' OR ';
      } elseif (strpos($q, ' AND)')) {
        $queryables[$i] = str_replace(' AND)', ')', $q) . ' AND ';
      }
    }
    // if there are no set terms, check for quotation search and set query_string with string escapes
    if (count($queryables) === 0 && preg_match('/^"(.*?)"/', $this->query)) {
      $search_escapes = [',', '-'];
      $search_replace = ['', ' '];
      $escaped = str_replace($search_escapes, $search_replace, $this->query);
      $this->es_query['body']['query']['function_score']['query']['bool']['must'][] =
        [
          'query_string' =>
          [
            "query" => $escaped,
            "default_operator" => "and",
          ]
        ];
    } else if (count($queryables) === 0 && $this->query === '*') {
      $this->es_query['body']['query']['function_score']['query']['bool']['must'][] =
        [
          'query_string' => [
            "query" => $this->query,
            "default_operator" => "and",
            "fuzzy_prefix_length" => 3,
            "fuzziness" => 1
          ]
        ];
    } else if (count($queryables) > 0) {
      // set query_string using the terms set above if there are any
      $queryString = '';
      foreach ($queryables as $q) {
        $queryString .= $q;
      }
      $this->es_query['body']['query']['function_score']['query']['bool']['must'][] =
        [
          'query_string' => [
            "query" => $queryString,
            "default_operator" => "and",
            "fuzzy_prefix_length" => 3,
            "fuzziness" => 1
          ]
        ];
    } else {
      // if no terms or quotation wrapped searches, use a more inclusive search approach. For catalog, queries with 2 words or less currently require all words to be present in the fields. 90% match is used for lonnger queries. Website and community have not been changed. They're large and unwieldy enough datasets that query strings still remain useful.
      $formats = [
        'catalog' => [
          'multi_match' => [
            "query" => $this->query,
            "fields" => ['title.folded^20', 'author.folded^10', 'artist.folded^10', 'callnum', 'callnums', 'subjects', 'series', 'addl_author', 'addl_title', 'title_medium'],
            "minimum_should_match" => "2<90%"
          ]
        ],
        'website' => [
          "query_string" => [
            "query" => $this->query,
            "fields" => [
              "*",
            ],
            "default_operator" => "and",
            "fuzzy_prefix_length" => 3,
            "fuzziness" => 1
          ]
        ],
        'community' => [
          "query_string" => [
            "query" => $this->query,
            "fields" => [
              "*",
            ],
            "default_operator" => "and",
            "fuzzy_prefix_length" => 3,
            "fuzziness" => 1
          ]
        ]
      ];
      $this->es_query['body']['query']['function_score']['query']['bool']['must'][] = $formats[$this->path_id];
    }
  }
  private function applyMatchTerms()
  {
    foreach ($this->args as $k => $v) {
      if (!array_key_exists($k, $this->matchables)) {
        continue;
      }
      $this->es_query['body']['query']['function_score']['query']['bool']['must'][] = $this->scaffoldQuery($this->matchables[$k], $k, $v);
    }
  }
  private function applyFilterTerms()
  {
    $defaults = [
      'catalog' => [
        'active' => [
          'type' => 'match',
          'value' => 1
        ]
      ],
      'website' => [
        'field_event_end' => [
          'type' => 'nested',
          'value' => [
            'bool' => [
              'must_not' => [
                'range' => [
                  'field_event_end' => [
                    'lte' => 'now',
                    'format' => 'date_hour_minute_second'
                  ]
                ]
              ]
            ]
          ]
        ],
        'mat_code' => [
          'type' => 'terms',
          'value' => ['page', 'story', 'pub_event', 'media']
        ]
      ],
      'community' => [
        'mat_code' => [
          'type' => 'terms',
          'value' => ['photo', 'issue', 'doc', 'article', 'media', 'advertisements']
        ]
      ]
    ];
    foreach ($defaults[$this->path_id] as $k => $d) {
      if (isset($_GET[$k])) {
        continue;
      } else {
        $this->es_query['body']['query']['function_score']['query']['bool']['filter'][] = ($d['type'] === 'nested' ? $d['value'] : [
          $d['type'] => [
            $k => $d['value']
          ]
        ]);
      }
    }
  }
  private function applySortTerms()
  {
    $defaults = [
      'catalog' => [],
      'website' => [
        'pub_event' => [
          'field_event_start' => [
            'order' => 'asc'
          ]
        ],
      ],
      'community' => []
    ];
    $nonKeyword = [
      'bib_created',
      'field_event_end',
      'field_event_start',
      'oldnews_date',
      'popular_week',
      'popular_month',
      'popular_year',
      'popular_alltime',
      '_score',
    ];
    if (isset($this->args['sort'])) {
      $sort_parts = explode('~', $this->args['sort']);
      $sort_field = $sort_parts[0];
      if (!in_array($sort_field, $nonKeyword)) {
        $this->es_query['body']['sort'] = [
          $sort_field . '.keyword' => [
            'order' => (isset($sort_parts[1]) ? $sort_parts[1] : 'desc') // Default sort decending
          ]
        ];
      } else {
        $this->es_query['body']['sort'] = [
          $sort_field => [
            'order' => (isset($sort_parts[1]) ? $sort_parts[1] : 'desc')
          ]
        ];
      }
    } elseif (isset($this->args['mat_code']) && isset($defaults[$this->path_id][$this->args['mat_code']])) {
      $this->es_query['body']['sort'] =  $defaults[$this->path_id][$_GET['mat_code']];
    }
    return $this->es_query;
  }
  private function enforceExactMatches($key, $value)
  {
    $this->es_query['body']['query']['function_score']['query']['bool']['must'][] = [
      'query_string' => [
        'query' => $key . ':' . $value,
      ]
    ];
  }
  private function applyFlatBoosts()
  {
    // Helps boost closer exact matches over partial matches in broader queries
    $this->es_query['body']['query']['function_score']['query']['bool']['should'][] = [
      'multi_match' =>
      [
        "query" => $this->query,
        "type" => "phrase_prefix",
        "fields" => ['title', 'author', 'artist'],
        "boost" => 500
      ]
    ];
    // Reduces Overdrive relevance compared to AADL-owned items
    $this->es_query['body']['query']['function_score']['query']['bool']['should'][] = [
      'bool' => [
        'should' =>
        [
          "match" => [
            "bib_type" => "Overdrive",
          ]
        ],
        "boost" => 0.7
      ]
    ];
  }
  private function handleOverdrive($value)
  {
    $this->es_query['body']['query']['function_score']['query']['bool']['must'][] = [
      'match' => [
        'author.folded' => $value,
      ]
    ];
  }
  private function scaffoldQuery($queryable, $key, $value)
  {
    if (isset($queryable['prepend'])) {
      $key = $queryable['prepend'] . '.' . $key;
    }
    if (isset($queryable['append'])) {
      $key = $key . '.' . $queryable['append'];
    }
    //mat_code bypasses .keyword, because the subfield is not configured on the website/local history index due to how elasticsearch_connector creates the index mapping.
    switch ($queryable['type']) {
      case 'date_range':
        $dates = explode(',', $value);
        return [
          'range' => [
            $key => [
              'gte' => $dates[0],
              'lte' => $dates[1]
            ]
          ]
        ];
        break;
      case 'lexile_range':
        $ceiling = 100 * ($value / 100 + 1) - 1;

        return [
          'range' => [
            $key => [
              'gte' => $value,
              'lte' => $ceiling
            ]
          ]
        ];
        break;
      case 'match':
        return [
          'match' => [
            $key => $value
          ]
        ];
        break;
      case 'prefix':
        return [
          'prefix' => [
            $key =>  [
              'value' => $value
            ]
          ]
        ];
        break;
      case 'simple':
        return [
          'simple_query_string' => [
            'query' => $value,
            'fields' => [$key]
          ]
        ];
        break;
      case 'match_phrase':
        return [
          'match_phrase' => [
            $key => [
              'query' => $value,
            ]

          ]
        ];
        break;
      case 'term':
        return [
          'term' => [
            $key  . (isset($queryable['keyword']) ? '' : '.keyword') => [
              'value' => $value
            ]
          ]
        ];
        break;
      case 'terms':
        return [
          'terms' => [
            $key . (isset($queryable['keyword']) ?  '' : '.keyword')  => explode(',', $value)
          ]
        ];
        break;
    }
  }
}
