<?php

namespace Drupal\Tests\arborelastic\Unit;

use Drupal\arborelastic\Search\Field\FieldClauseBuilder;
use Drupal\arborelastic\Search\Query\AnalyzedQuery;
use Drupal\arborelastic\Search\Score\QueryBoostBuilder;
use Drupal\Tests\UnitTestCase;

/*
  Builder types are only set in profile code, so an unknown type is a typo and should fail loudly
  rather than silently dropping a clause or boost.
*/
class BuilderTypeTest extends UnitTestCase
{
  public function testUnknownMatchableTypeThrows(): void
  {
    $this->expectException(\LogicException::class);
    (new FieldClauseBuilder())->build(['type' => 'mtach'], 'title', 'dune');
  }

  public function testUnknownQueryBoostTypeThrows(): void
  {
    $this->expectException(\LogicException::class);
    (new QueryBoostBuilder())->build(['type' => 'number_mtach'], new AnalyzedQuery('season 4'));
  }

  public function testQueryBoostWithNothingToBoostReturnsNull(): void
  {
    $boost = ['type' => 'number_match', 'fields' => ['title'], 'weight' => 3];
    $this->assertNull((new QueryBoostBuilder())->build($boost, new AnalyzedQuery('dune')));
  }
}
