<?php

namespace Drupal\arborelastic\Search\Debug;

/*
  For dev debug: logs which named clauses (see QueryNamer) matched each hit on the first page of results.
  Append named_queries=1 to any search for this to log, run `drush en dblog` in the container locally,
  and look for the arborelastic type at /admin/reports/dblog.

  Clauses listed are ones that matched, not necessarily ones that counted toward the score (a dis_max
  only scores its best child). This is not a full replacement for /_explain. If you need extensive
  debug, using an _explain request is recommended.
*/

class MatchedQueryLogger
{
  public function log(string $pathId, string $query, int $offset, array $hits): void
  {
    $lines = [];
    foreach ($hits as $i => $hit) {
      $lines[] = sprintf(
        '%d. %s [%s] score %s: %s',
        $offset + $i + 1,
        $hit['_source']['title'] ?? '(no title)',
        $hit['_id'],
        isset($hit['_score']) ? sprintf('%.2f', $hit['_score']) : '-',
        implode(', ', $hit['matched_queries'] ?? ['(none)'])
      );
    }
    \Drupal::logger('arborelastic')->debug('Matched queries for @path_id search "@query":<pre>@hits</pre>', [
      '@path_id' => $pathId,
      '@query' => $query,
      '@hits' => implode("\n", $lines),
    ]);
  }
}
