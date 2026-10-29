# arborelastic

Drupal module that builds and runs AADL's Elasticsearch searches for the catalog, the website (pages, stories, events) and community/local history (Old News, photos).

Requires the official Elasticsearch PHP client:

```sh
composer require elasticsearch/elasticsearch
```

## Configuration

Settings live at **/admin/config/arborelastic** (`arborelastic.settings`):

Hook up path_id to the index or alias like this: {path_id}:{alias} 

### Request flow

`ArborElasticQuery::buildRequest()` starts from the profile's base query and applies these steps in order:

1. **Default filters.** These come from `getDefaultFilters()`, e.g. `active: 1` for the catalog or the default `mat_code` set for the index (most relevant for website/local history). A default filter is skipped when the same key is provided by user.
2. **Query terms.** The search text, handled by the first rule that applies:
   1. **Inline fields** (`title:clay author:chabon`): `InlineFieldQueryParser` splits the text into `Segment`s, and `InlineClauseBuilder` turns them into `must` and `must_not` clauses.
   2. **Quoted search** (`"star trek"`): a strict `query_string` on the profile's `getQuotedFields()`, with each field's own search analyzer.
   3. **`*`**: matches everything, used for browse-style lists such as fesliders.
   4. **Operator query**: the profile's `getOperatorQuery()`. For the catalog this applies when the text contains ` AND `, ` OR ` or `*`.
   5. **Broad fallback**: `getFallbackQuery()`. This is the broader relevance query when no other rules apply.
3. **Match terms.** Each arg in `getMatchableFields()` becomes a clause in `FieldClauseBuilder`.
4. **Sort.** `sort=field~asc|desc`, otherwise the profile's default sort for the selected `mat_code`. Fields listed in `getDirectSortFields()` (dates, popularity counts) are sorted directly. Everything else is sorted on its `.keyword` subfield.
5. **Exact phrase boost.** On broad searches without quotes of their own.
6. **Query boosts.** Broad searches only. These boosts are separate fromt he global boosts, like overdrive and popularity.

Step 2 is a thin `QueryPlan` holding the text the later steps work from, and applies inline rules `QueryPlan::allowsRelevanceBoosts()` turns steps 5 and 6 off for inline-field and wildcard searches, and `allowsExactPhraseBoost()` also turns step 5 off for text that already contains quotes.

`EsQueryDocument` wraps the request array, so callers use `addMust()`, `addFilter()`, `addFunction()` and similar instead of repeating the `body.query.function_score.query.bool` path. Everything lives inside that top-level `function_score`.

### IndexProfile

Each `path_id` has a profile implementing `IndexProfileInterface`. Profiles are the main place to change how an index is searched:

| Method | Controls |
|---|---|
| `getBaseQuery()` | Request scaffold: base `function_score` functions (popularity, recency, etc.) and aggregations |
| `getSearchableFields()` | Fields allowed in inline `field:value` syntax, and how each is queried |
| `getMatchableFields()` | Args that become clauses, with a `FieldClauseBuilder` type |
| `getDefaultFilters()` | Filters applied unless overridden by an arg |
| `getDefaultSorts()`, `getDirectSortFields()` | Sortables |
| `getQuotedFields()` | Fields for quoted searches (`null` = every field). Only fields that keep a phrase exact: no trigram, edge-ngram or stemmed subfields |
| `getExactPhraseBoost()` | Fields and boost for the exact phrase clause |
| `getOperatorQuery()`, `getFallbackQuery()` | Free-text query construction |
| `getQueryBoosts()` | Text-triggered boosts (separate from globals) |

`AbstractIndexProfile` provides simple defaults, which `WebsiteIndexProfile` and `CommunityIndexProfile` mostly use as-is. `CatalogIndexProfile` overrides most of them, so the structure will be revisited if Website/COmmunity move away from search-api drupal module.

**The catalog broad/fallback query** is a `bool.should` of five named clauses, with `minimum_should_match: 2`:

- `broad`: `title` and `title_medium` scored as one field, plus a `multi_match` across author, subjects, call numbers and so on. Written forms of numbers are added to the title query, so "season four" matches "Season 4".
- `combined`: `combined_fields` with the stop words (a, an, the) stripped. This helps queries that mix title and author words.
- `title_prefix`, `author_prefix`: `phrase_prefix` matches.
- `fuzzy`: trigram matches and `~1` fuzzy matches on words of 4+ characters.

Requiring two of the five keeps a single loose match (often `combined`) from letting poor matches through.

**Searchable field categories** (from `getSearchableFields()`):

- `foldables`: queried on `{field}.folded`. A quoted value also gets an exact-match clause.
- `stemmed`: queried on `{field}.stem`.
- `term`: a quoted value also gets a case-insensitive `term` on `.keyword`.
- `names` ("standalone" fields, e.g. `author`): matched by their own lenient clause outside the `query_string`, with initials joined ("J. K." becomes "J.K."), so OverDrive name formats also match.

### Parser

The inline parser supports `AND`, `OR`, `NOT`, `AND NOT` and `OR NOT` between fields:

- When a inline (name) field is present, `NOT` binds tightest, then `AND`, then `OR`, so `a AND b OR c` means `(a AND b) OR c`.
- Without a inline field, the fields are joined into a single `query_string`, and Elasticsearch handles the operators.
- Illogical queries with no positive terms (`NOT author:chabon`) returns no results rather than "everything except" to reduce strain.
- Unrecognized prefixes (`foo:bar`, `Title:dune`) are treated as free text, with the colon escaped.

`InlineParseResult` carries the segments, any free text typed before the first field (`remainder`), and the search text with standalone prefixes removed.

### Field

`FieldClauseBuilder` turns an arg into a clause based on its matchable `type`:

- Supported types: `match`, `match_phrase`, `term`, `terms` (comma-separated values), `date_range` (`from,to`), `lexile_range` (a 100-point band, e.g. `800` gives 800–899), `prefix`, `simple` and `custom`. The last three aren't used by any profile yet.
- `prepend` and `append` adjust the field path, e.g. `flags.request_only` or `reading_level.lexile`.
- `keyword: false` skips the `.keyword` suffix on `term` and `terms`.

### Query

- `QueryPlan` and `EsQueryDocument` are described under *How a request is built*. They help separate the profiles from the query.
- `AnalyzedQuery` assembles versions of the search text that the clause and boost builders use:
  - `lower`: lowercased text
  - `escaped`: commas removed, hyphens turned into spaces
  - `stopStripped`: text without the stop words passed to the constructor
  - `critical`: words of 4+ characters
  - `numbers`: each standalone number mapped to its digit, ordinal and written forms (`4 => [4, 4th, four, fourth]`; written forms go up to 20)

### Score

`QueryBoostBuilder` turns entries from `getQueryBoosts()` into `function_score` functions. There is only one at the moment, but these will multiply with the profile's base functions, such as popularity.

- `number_match`: multiplies by `weight` for records where any number in the query appears in the given fields, in any of its forms. The catalog uses weight 3 across `title`, `title_medium`, `callnums` and `series`, so "Star Trek Season 4" ranks season 4 above more popular seasons.

To add a boost type, add a `case` to `QueryBoostBuilder::build()`, then reference it from a profile's `getQueryBoosts()`.

## Debugging: named queries

Add `named_queries=1` to any search URL for debugging assistance:

```
/search/catalog/star%20trek%20season%204?named_queries=1
```

This does two things:

1. **Clauses get `_name` labels.** `QueryNamer` labels each clause, and Elasticsearch reports which named clauses matched each hit. Nested clauses are prefixed with their parent's name, so you can tell which part of the query matched:

   ```
   exact_phrase, broad:title, broad:fields, combined:fields, combined:notes,
   title_prefix, author_prefix, fuzzy:title_trigram, fuzzy:author_trigram, fuzzy:critical_fuzz
   ```

   Without the flag, `QueryNamer` returns clauses unchanged, so normal requests carry no labels.

2. **Matched clauses are logged.** `MatchedQueryLogger` writes one debug log entry for the page of results, with one line per hit:

   ```
   1. Star Trek: Deep Space Nine. Season 4 [123456] score 812.40: exact_phrase, broad:title, combined:fields, title_prefix
   ```

   Enable `dblog` locally (`drush en dblog`) and filter the recent log messages by type **arborelastic**.

The listed clauses are the ones that matched, not necessarily the ones that counted toward the score. A `dis_max`, for example, only scores its best clause. For exact score breakdowns, run the request against Elasticsearch's `_explain` or with `"explain": true`.

When adding a clause to a profile, wrap it in `$this->namer->named('name', ...)`, or build sibling clauses with `$this->namer->namedClauses([...])`, so it shows up in these logs.

The flag is read from the request's query string, so it works on any environment, including production. It only adds labels and a debug log entry and doesn't change ranking.

## Tests

The tests check **request structure, not ranking**. They catch any change to the request sent to Elasticsearch. They can't tell whether results got better or worse. For that, run real searches against an index (see *Debugging: named queries*).

Run them from the docker-aadlorg repo root:

```sh
docker exec -w /var/www/aadlorg aadlorg-php vendor/bin/phpunit -c web/core web/modules/custom/arborelastic/tests
```

Add `--filter 'Season 4'` to run only the cases whose label matches (labels look like `#0 catalog: Star Trek Season 4`), or `--testdox` to list each case by name. Elasticsearch and Drupal config aren't needed.

**RequestSnapshotTest** rebuilds the request for each case in `tests/fixtures/requests.json` and checks that it's byte-identical to the recorded one. The cases cover free text, quoted, wildcard and inline-field searches, args, `named_queries`, and the website and community profiles. **BuilderTypeTest** checks that unknown clause and boost types throw.

- **Refactoring:** if the suite passes, every recorded request is unchanged.
- **Intentional request changes:** regenerate the fixtures, then check that only the cases you expected changed. Commit the fixture diff with the code change.

  ```sh
  docker exec -w /var/www/aadlorg aadlorg-php drush php:script modules/custom/arborelastic/tests/fixtures/regenerate.php
  git diff -- tests/fixtures/requests.json
  ```

- **Adding a case:** append `{"path_id": "catalog", "query": "rocky II", "args": []}` to `requests.json` without a `request` key, run `regenerate.php`, then review and commit the generated request. Existing cases keep the index name they were recorded with, and new cases take it from local config.
