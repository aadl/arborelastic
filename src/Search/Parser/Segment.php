<?php

namespace Drupal\arborelastic\Search\Parser;

/*
  One recognized inline field from a search ("title:clay"), in the order it was typed.
*/

class Segment
{
  public function __construct(
    // inline key as typed: title, author, ...
    public readonly string $field,
    // matched by its own clause (in extras) rather than inside the query_string: the profile's name fields, e.g. author
    public readonly bool $standalone,
    // query_string fragment, e.g. "title.folded:(clay)"; null when standalone
    public readonly ?string $queryable,
    // other clauses the field requires: the lenient name match, exact match for quoted values
    public readonly array $extras,
    /*
      operator typed between this field's value and the next field: AND, OR, NOT, AND NOT, OR NOT,
      or null (implicit AND). Operators inside a value stay in it: "title:harry AND potter" is one segment.
    */
    public readonly ?string $operator,
    // negated by the previous segment's operator, or by a NOT typed before the first field
    public readonly bool $not = false,
  ) {}

  public function negated(): self
  {
    return new self($this->field, $this->standalone, $this->queryable, $this->extras, $this->operator, true);
  }
}
