<?php

namespace Drupal\arborelastic\Search\IndexProfile;

class WebsiteIndexProfile extends AbstractIndexProfile
{
  public function getPathId(): string
  {
    return 'website';
  }

  public function getSearchableFields(): array
  {
    return [
      'fields' => ['title', 'location_name'],
      'foldables' => [],
      'stemmed' => [],
      'term' => [],
      'names' => [],
    ];
  }

  public function getMatchableFields(): array
  {
    return [
      'mat_code' => ['type' => 'terms', 'keyword' => false],
      'location_name' => ['type' => 'match'],
      'event_ages' => ['type' => 'terms'],
      'event_categories' => ['type' => 'terms', 'keyword' => false],
    ];
  }

  public function getDefaultFilters(): array
  {
    return [
      'field_event_end' => [
        'type' => 'raw',
        'value' => [
          'bool' => [
            'must_not' => [
              'range' => [
                'field_event_end' => [
                  'lte' => 'now',
                  'format' => 'date_hour_minute_second',
                ],
              ],
            ],
          ],
        ],
      ],
      'mat_code' => [
        'type' => 'terms',
        'value' => ['page', 'story', 'pub_event', 'media'],
      ],
    ];
  }

  public function getDefaultSorts(): array
  {
    return [
      'pub_event' => [
        'field_event_start' => [
          'order' => 'asc',
        ],
      ],
    ];
  }

  public function getDirectSortFields(): array
  {
    return ['bib_created', 'field_event_start', 'field_event_end'];
  }
}
