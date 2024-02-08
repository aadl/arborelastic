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
	public function __construct($path_id, $query, $args)
	{
		$this->args = $args;
		$this->query = $query;
		$this->path_id = $path_id;
		$formats = [
			'catalog' => [
				'index' => 'bibs',
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
				'index' => 'elasticsearch_index_migration_nodes',
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
				'index' => 'elasticsearch_index_migration_nodes',
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
		// print(json_encode($this->es_query['body']));
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
		$search_fields = [
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
			'title'
		];
		$foldables = [
			'title',
			'author',
			'artist'
		];
		$queryables = [];

		// match all : separated terms and iterate over the resulting key value pairs. Concatenate a query_string for as many as are supplied, checking against folded fields
		preg_match_all('/([a-z]\S*):(["\'\da-z\s].+?(?=(?:[a-z]\S*:|$)))/', $this->query, $matches);
		$keys = $matches[1];
		$values = $matches[2];

		// place terms into array to then iterate over again to check for operators. Doing this separately is less efficient but easier to handle.
		foreach ($keys as $i => $k) {
			if (in_array($k, $search_fields)) {
				if (in_array($k, $foldables)) {
					$queryables[] = $k . '.folded:(' . trim($values[$i]) . ')';;
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
		if (count($queryables) === 0 && preg_match('/"(.*?)"/', $this->query)) {
			$search_escapes = [',', '-'];
			$search_replace = ['', ' '];
			$escaped = str_replace($search_escapes, $search_replace, $this->query);
			$this->es_query['body']['query']['function_score']['query']['bool']['must'][] =
				[
					'query_string' =>
					[
						"query" => $escaped,
						"default_operator" => "and",
						"fuzzy_prefix_length" => 3,
						"fuzziness" => 1
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
		$matchables = [
			'catalog' => [
				'available_branches' => ['type' => 'term'],
				'mat_code' => ['type' => 'terms'],
				'lang' => ['type' => 'term'],
				'nonfiction' => ['type' => 'term'],
				'ages' => ['type' => 'terms'],
			],
			'community' => [
				'old_news_taxonomy' => ['type' => 'terms'],
				'mat_code' => ['type' => 'terms', 'keyword' => true],
				'oldnews_date' => [
					'type' => 'range',
				],
				'photo_indexed' => [
					'type' => 'term'
				]
			],
			'website' => [
				'mat_code' => ['type' => 'terms', 'keyword' => true],
				'location_name' => ['type' => 'term'],
				'event_ages' => ['type' => 'terms'],
				'event_categories' => ['type' => 'terms'],
			]
		];
		foreach ($this->args as $k => $v) {
			if (!array_key_exists($k, $matchables[$this->path_id])) {
				continue;
			}
			$this->es_query['body']['query']['function_score']['query']['bool']['must'][] = $this->scaffoldQuery($matchables[$this->path_id][$k], $k, $v);
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
			'oldnews_date'
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
	private function scaffoldQuery($queryable, $key, $value)
	{
		switch ($queryable['type']) {
			case 'range':
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
			case 'match':
				return [
					'match' => [
						$key => $value
					]
				];
				break;
			case 'term':
				return [
					'term' => [
						$key . (isset($queryable['keyword']) ? '' : '.keyword') => [
							'value' => $value
						]
					]
				];
				break;
			case 'terms':
				return [
					'terms' => [
						$key . (isset($queryable['keyword']) ? '' : '.keyword')  => explode(',', $value)
					]
				];
				break;
		}
	}
}
