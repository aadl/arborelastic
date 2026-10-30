<?php

namespace Drupal\arborelastic\Search\Query;
/*
construct the query, creating the php-side analyzed strings to append in subsequent elastic clauses 
*/

class AnalyzedQuery
{
  protected const NUMBER_WORDS = [
    1 => ['one', 'first'],
    2 => ['two', 'second'],
    3 => ['three', 'third'],
    4 => ['four', 'fourth'],
    5 => ['five', 'fifth'],
    6 => ['six', 'sixth'],
    7 => ['seven', 'seventh'],
    8 => ['eight', 'eighth'],
    9 => ['nine', 'ninth'],
    10 => ['ten', 'tenth'],
    11 => ['eleven', 'eleventh'],
    12 => ['twelve', 'twelfth'],
    13 => ['thirteen', 'thirteenth'],
    14 => ['fourteen', 'fourteenth'],
    15 => ['fifteen', 'fifteenth'],
    16 => ['sixteen', 'sixteenth'],
    17 => ['seventeen', 'seventeenth'],
    18 => ['eighteen', 'eighteenth'],
    19 => ['nineteen', 'nineteenth'],
    20 => ['twenty', 'twentieth'],
  ];

  public readonly string $lower;
  public readonly string $escaped;
  public readonly string $stopStripped;
  public readonly array $critical;
  // standalone digits in the query, each expanded with its written and ordinal forms (4 => 4, 4th, four, fourth)
  public readonly array $numbers;

  public function __construct(public readonly string $raw, array $stopWords = [])
  {
    $this->lower = strtolower($raw);
    $this->escaped = str_replace([',', '-'], ['', ' '], $raw);
    $this->critical = array_filter(explode(' ', $this->escaped), function ($w) {
      return strlen($w) >= 4;
    });

    $stripped = $raw;
    foreach ($stopWords as $s) {
      $stripped = preg_replace('/(^|\s)\b(?i)' . $s . '\b(\s|$)/', ' ', $stripped);
    }
    $this->stopStripped = $stripped;

    preg_match_all('/\b\d+\b/', $raw, $matches);
    $numbers = [];
    foreach (array_unique($matches[0]) as $n) {
      $numbers[$n] = array_merge([$n, $n . $this->ordinalSuffix((int) $n)], self::NUMBER_WORDS[(int) $n] ?? []);
    }
    $this->numbers = $numbers;
  }

  private function ordinalSuffix(int $n): string
  {
    if (in_array($n % 100, [11, 12, 13])) {
      return 'th';
    }
    return match ($n % 10) {
      1 => 'st',
      2 => 'nd',
      3 => 'rd',
      default => 'th',
    };
  }
}
