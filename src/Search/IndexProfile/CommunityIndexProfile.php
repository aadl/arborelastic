<?php

namespace Drupal\arborelastic\Search\IndexProfile;

class CommunityIndexProfile extends AbstractIndexProfile
{
  public function getPathId(): string
  {
    return 'community';
  }

  public function getSearchableFields(): array
  {
    return [
      'fields' => ['title', 'photographer', 'authored_by'],
      'foldables' => [],
      'stemmed' => [],
      'term' => [],
      'names' => [],
    ];
  }

  public function getMatchableFields(): array
  {
    return [
      'old_news_taxonomy' => ['type' => 'terms'],
      'mat_code' => ['type' => 'terms', 'keyword' => false],
      'oldnews_date' => ['type' => 'date_range'],
      'photo_indexed' => ['type' => 'term'],
    ];
  }

  public function getDefaultFilters(): array
  {
    return [
      'mat_code' => [
        'type' => 'terms',
        'value' => ['photo', 'issue', 'doc', 'article', 'media', 'advertisements'],
      ],
    ];
  }

  public function getDirectSortFields(): array
  {
    return ['bib_created', 'oldnews_date'];
  }
}
