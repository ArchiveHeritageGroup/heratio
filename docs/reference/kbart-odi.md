# KBART, OpenURL Resolver and ODI Quality Scorecard

**Summary.** Heratio's library module (`packages/ahg-library/`) provides three
discovery-interoperability features aligned with the NISO Open Discovery
Initiative (ODI) recommended practice and the OpenURL 1.0 standard:

1. **KBART export** - a 43-column KBART (Knowledge Bases And Related Tools) TSV
   export of the library holdings, plus remote-feed import. (Already shipped.)
2. **OpenURL 1.0 link resolver** - a `GET /api/resolver` endpoint that takes an
   OpenURL Key/Encoded-Value (KEV) citation and either redirects to the matching
   catalogue record or returns an OpenURL ContextObject XML document.
3. **ODI quality scorecard** - a per-collection conformance scorecard computing
   the four headline ODI metrics (link-resolver presence, open-access share,
   preprint indexing, ORCID coverage) and a composite quality score.

This document covers the OpenURL resolver and the ODI scorecard. KBART export is
documented in `kbart-remote-implementation.md`; the BIBFRAME/ODI conformance
mapping is in `bibframe-odi-conformance.md`.

---

## OpenURL 1.0 Link Resolver

### Purpose

A link resolver lets any discovery tool, citation manager, or A-and-I database
that emits an OpenURL (Google Scholar, Crossref, EBSCO, etc.) point a patron at
the corresponding full record in the local library catalogue. The patron clicks
a "Find it @ your library" link; the resolver matches the citation against local
holdings and routes them to the record or to an intermediate menu.

### Endpoint

```
GET /api/resolver?<OpenURL 1.0 KEV parameters>
```

### Accepted parameters

The resolver parses OpenURL 1.0 KEV parameters. Both dotted (`rft.title`) and
underscore (`rft_title`) variants are accepted:

| Parameter | Meaning |
|-----------|---------|
| `rft.title`  | Title (book or generic) |
| `rft.jtitle` / `rft.btitle` / `rft.atitle` | Journal / book / article title (title fallbacks) |
| `rft.au` (or `rft.aulast` + `rft.aufirst`) | Author |
| `rft.isbn`   | ISBN (hyphens/spaces stripped on normalisation) |
| `rft.issn` / `rft.eissn` | ISSN / electronic ISSN (normalised to NNNN-NNNN) |
| `rft.date`   | Publication date |
| `rft.pub`    | Publisher |
| `rft.volume` / `rft.issue` / `rft.spage` | Volume / issue / start page |
| `rft.doi`    | DOI |
| `rft_id`     | One or more identifier URIs: `urn:isbn:...`, `urn:issn:...`, `info:doi/...`, `info:oai/...`, `https://doi.org/...` |

### Matching strategy

The resolver matches against `library_item` in decreasing order of specificity
and stops at the first layer that yields hits:

1. **ISBN** - normalised exact match on `library_item.isbn`.
2. **DOI** - exact match on `library_item.doi`.
3. **ISSN / eISSN** - exact match on `library_item.issn`.
4. **Title** - exact match on `information_object_i18n.title`, then a prefix
   (`LIKE 'title%'`) match. LIKE wildcards in the input are escaped.

### Responses

| Outcome | HTTP status | Body |
|---------|-------------|------|
| Exactly one match | `303 See Other` | `Location: /library/{slug}` |
| Multiple matches | `300 Multiple Choices` | OpenURL ContextObject XML listing candidates |
| No match | `404 Not Found` | OpenURL ContextObject XML (zero candidates) |
| No usable parameters | `400 Bad Request` | OpenURL ContextObject XML (empty) |

The ContextObject XML uses the `info:ofi/fmt:xml:xsd:ctx` namespace. It echoes
the parsed citation context inside `ctx:metadata` and lists any candidate
records (id, resolved URL, title) inside a `ctx:resolution` element with a
`matches` count attribute.

### Code

| Concern | File |
|---------|------|
| HTTP entry point | `packages/ahg-library/src/Controllers/ResolverController.php` |
| Parsing + matching + XML | `packages/ahg-library/src/Services/OpenUrlResolverService.php` |
| Unit tests | `packages/ahg-library/tests/Unit/OpenUrlResolverServiceTest.php` |

The parsing, identifier decoding, ISBN/ISSN normalisation and XML serialisation
layers are pure functions with no database dependency, so they are unit tested
without a database connection. Only `resolve()` / `findMatches()` touch the
catalogue.

---

## ODI Quality Scorecard

### Purpose

The NISO Open Discovery Initiative recommends that content providers and
libraries expose a small set of quality and transparency metrics. The scorecard
computes these per **collection** and presents them in an admin view so a
library can see, at a glance, how discoverable and standards-conformant each
collection is.

A "collection" is the parent `information_object` that groups one or more
`library_item` rows (standard archival/MPTT hierarchy). No new collection table
is introduced; the parent IO id is the collection id.

### Metrics

| Metric | Column | Source |
|--------|--------|--------|
| Link resolver present | `link_resolver_present` | True platform-wide once the resolver endpoint exists (per-collection override hook provided) |
| Open-access percentage | `oa_percentage` | Share of items whose `material_type` is in the `library_oa_material_type` dropdown taxonomy; falls back to "has a DOI" as a proxy when that taxonomy is empty |
| Preprints indexed | `preprints_indexed` | Count of items whose `material_type` is in the `library_preprint_material_type` taxonomy, else `material_type LIKE '%preprint%'` |
| ORCID in records | `orcid_in_records` | Count of items with a creator (`library_item_creator.actor_id`) carrying an `orcid` row in `ahg_actor_identifier` |

Dropdown taxonomies are read from `ahg_dropdown` (columns `taxonomy`, `code`) -
never hardcoded ENUMs.

### Composite quality score

`quality_score` is a weighted composite on a 0-100 scale. Each component is
normalised to 0..1 and weighted:

| Component | Weight |
|-----------|--------|
| Open-access share | 0.35 |
| Link-resolver presence | 0.25 |
| ORCID coverage (capped at item_count) | 0.25 |
| Preprint indexing (capped at item_count) | 0.15 |

Coverage ratios (preprints, ORCID) are computed against the collection's item
count and capped at 1.0. Out-of-range inputs are clamped. An empty collection
scores 0.

### Storage

Scores are cached in the `library_odi_collection` table
(migration `2026_06_04_000101_create_library_odi_collection_table.php`):

```
id, collection_id (unique), collection_title, item_count,
link_resolver_present, oa_percentage, preprints_indexed,
orcid_in_records, quality_score, updated_at
```

### Refreshing scores

The scorecard is recomputed by the console command:

```
php artisan ahg:library-odi-refresh
```

It iterates every collection, computes the metrics, and upserts into
`library_odi_collection`. The admin UI also exposes a "Recompute scores" button
that runs the same refresh on demand.

### Admin UI

```
GET  /library-manage/odi/scorecard          (list with scores)
POST /library-manage/odi/scorecard/refresh  (recompute on demand)
```

The view (`packages/ahg-library/resources/views/odi/scorecard.blade.php`,
Bootstrap 5, extends `theme::layouts.1col`) lists collections with each metric
and a colour-coded quality-score badge (green >= 75, amber >= 50, red below).

### Code

| Concern | File |
|---------|------|
| Metric computation + scoring + persistence | `packages/ahg-library/src/Services/OdiScorecardService.php` |
| Admin UI controller | `packages/ahg-library/src/Controllers/OdiScorecardController.php` |
| Refresh command | `packages/ahg-library/src/Console/Commands/OdiRefreshScorecardCommand.php` |
| Table migration | `packages/ahg-library/database/migrations/2026_06_04_000101_create_library_odi_collection_table.php` |
| View | `packages/ahg-library/resources/views/odi/scorecard.blade.php` |
| Unit tests | `packages/ahg-library/tests/Unit/OdiScorecardServiceTest.php` |

The scoring arithmetic (`computeQualityScore`) is a pure function and is unit
tested without a database. The aggregation helpers read from `library_item`,
`library_item_creator` and `ahg_actor_identifier`.

---

## Standards references

- OpenURL 1.0: ANSI/NISO Z39.88 (KEV ContextObject format).
- ODI: NISO RP-19, Open Discovery Initiative recommended practice.
- KBART: NISO RP-9, Knowledge Bases And Related Tools.
