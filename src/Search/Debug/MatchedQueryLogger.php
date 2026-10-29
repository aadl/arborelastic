<?php

namespace Drupal\arborelastic\Search\Debug;

/*
  For dev debug. logs named clauses matched on each hit on the first page of results.
  Add named_queries=1 to any search for this to log, run `drush en dblog` locally. Look for arborelastic type

  Clauses listed are ones that matched, not necessarily ones that counted toward the score (a dis_max
  only scores its highest ranking query). The _explain endpoint will better diagnose specific record-level issues
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
