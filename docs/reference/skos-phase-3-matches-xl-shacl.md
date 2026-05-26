# SKOS Phase 3 - Cross-vocab matches, SKOS-XL, and SHACL validation (Heratio)

Heratio issue #661 Phase 3, shipped 2026-05-26. Builds on Phase 1 (altLabel + scopeNote completeness, v1.82.0) and Phase 2 (Turtle / N-Triples / JSON-LD exporters, v1.77.0).

This phase adds:

1. **Cross-vocabulary matches** - link a Heratio term to a concept in any other SKOS-shaped vocabulary via the five SKOS mapping predicates (`exactMatch`, `closeMatch`, `broadMatch`, `narrowMatch`, `relatedMatch`).
2. **SKOS-XL labels** - opt-in emission of `skosxl:Label` resources alongside the existing plain-literal `skos:prefLabel` / `altLabel` / `hiddenLabel` triples. Triggered by `?skos_xl=1` on the export endpoint.
3. **SHACL validation** - new `skos:validate` artisan command that checks every concept against a focused four-rule SHACL profile (in pure PHP). Vendored shapes file at `packages/ahg-term-taxonomy/resources/shacl/skos-shapes.ttl` documents the rules and acts as a forward-compatibility hook for the Phase 4 full-SHACL engine.

Graph visualisation (Cytoscape / hierarchical viewer) is queued for Phase 4. Phase 3 is the data + validation foundation; Phase 4 is the UI surface.

---

## 1. Cross-vocabulary matches

### Database

New table `ahg_term_cross_match` (auto-installed by the package service provider on first boot if missing - same `Schema::hasTable` + `DB::unprepared(install.sql)` pattern as every other package):

```sql
CREATE TABLE ahg_term_cross_match (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  term_id      INT NOT NULL,
  match_type   VARCHAR(16) NOT NULL,      -- exactMatch | closeMatch | broadMatch | narrowMatch | relatedMatch
  target_uri   VARCHAR(512) NOT NULL,
  target_label VARCHAR(255) DEFAULT NULL,
  target_vocab VARCHAR(255) DEFAULT NULL, -- "LCSH" | "AAT" | "Wikidata" | ...
  confidence   DECIMAL(3,2) DEFAULT NULL, -- 0.00..1.00 for automated rows
  source       VARCHAR(32)  NOT NULL DEFAULT 'manual',  -- manual | getty | loc | wikidata | automated
  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_atcm_term       (term_id),
  KEY idx_atcm_match_type (match_type),
  KEY idx_atcm_target     (target_uri(255))
);
```

### Admin UI

Term-edit form (`/term/{slug}/edit`) gains a **Cross-vocabulary matches** accordion panel. Multi-row table with one row per match link; the JS adds/removes rows in the same idiom as scope / source / display notes. Form post-processes via `CrossMatchService::replaceAll($termId, $rows)` which is a single delete + bulk insert (avoids partial-write surprises).

### Exporter

`TermController::exportSkos` now pulls cross-match rows in a single batched lookup (no N+1 across the concept walk) and feeds them to all four serialisers. Each row produces one triple:

| Format | Output |
|---|---|
| RDF/XML | `<skos:exactMatch rdf:resource="..."/>` |
| Turtle | `skos:exactMatch <...> ;` |
| N-Triples | `<term-uri> <http://www.w3.org/2004/02/skos/core#exactMatch> <target> .` |
| JSON-LD | `"skos:exactMatch": [{"@id": "..."}]` (grouped by predicate) |

A row with an unknown `match_type` value collapses to `skos:closeMatch` as a safe default (`mapPredicate()` in the controller).

### Service API

`AhgTermTaxonomy\Services\CrossMatchService`:

- `forTerm(int $termId): array` - rows for a single term (admin UI)
- `forTerms(array $termIds): array` - batched lookup keyed by term id (exporter)
- `create(int $termId, string $matchType, string $targetUri, array $opts = []): int` - validates type + URL + confidence range
- `replaceAll(int $termId, array $rows): int` - delete + re-insert (form post)
- `delete(int $termId, int $matchId): int` - scoped single-row delete

---

## 2. SKOS-XL labels

SKOS-XL (`http://www.w3.org/2008/05/skos-xl#`) promotes labels from plain RDF literals to first-class resources with their own URIs, provenance, and history. The W3C spec ships them as `skosxl:Label` instances pointed at by `skosxl:prefLabel` / `skosxl:altLabel` / `skosxl:hiddenLabel`.

Heratio emits XL **alongside** the existing plain literals - never as a replacement. This keeps legacy consumers (search indexers, OAI harvesters) working unchanged. Opt in by adding `?skos_xl=1` to the export endpoint:

```
GET /term/export/skos/turtle?taxonomy=35&skos_xl=1
```

URI template for an XL label resource:

```
<base>/term/{term_id}/label/{lang}-{type}/{slugified-literal}
```

`{type}` is one of `pref`, `alt`, `hidden`. Stable and deterministic - re-running the export produces the same URIs.

Each `skosxl:Label` resource carries:

- `rdf:type skosxl:Label`
- `skosxl:literalForm` with the language-tagged literal
- `dct:created` (export timestamp - good-enough provenance until Phase 4 records the actual editor + edit history)
- `dct:creator "heratio"`

---

## 3. SHACL validation (minimal subset)

Full SHACL evaluation needs either pyshacl (Python sidecar) or an easyrdf+ARC2 pipeline. Both add deployment weight and a heavy dependency tree. Phase 3 ships the validator as **pure PHP enforcing four focused rules**, with the vendored shapes file documenting the full set so Phase 4 can swap in pyshacl without changing the wire surface.

### Rules enforced today

| Shape | Rule | Source |
|---|---|---|
| `S1-MinPrefLabel` | Every `skos:Concept` MUST have at least one `skos:prefLabel`. | SKOS Reference S14 |
| `S2-UniqueLangPrefLabel` | A concept MUST NOT have more than one `prefLabel` in the same language. | SKOS Reference S14 |
| `S3-PrefAltDisjoint` | A concept MUST NOT have `prefLabel` + `altLabel` with the same literal+lang. | SKOS Reference S13 |
| `S4-NoBroaderCycles` | `skos:broader` MUST NOT form a transitive cycle. | SKOS Reference inferred from `broaderTransitive` irreflexivity |

### Vendored shapes file

`packages/ahg-term-taxonomy/resources/shacl/skos-shapes.ttl` - turtle, ~80 lines, distilled from the W3C SKOS Reference appendix. Not loaded at runtime today; declared as canonical for the Phase 4 pyshacl wire-up.

### Artisan command

```
php artisan skos:validate                     # all taxonomies
php artisan skos:validate --taxonomy=35       # one taxonomy
php artisan skos:validate --taxonomy=35 --json  # JSON output for CI
```

Exit code is `0` if no violations are found, `1` otherwise. The command pulls every culture out of `term_i18n` so the S2 uniqueness check sees all the language variants at once - the exporter only sees one culture per call, so the artisan path is the canonical place to enforce S2.

---

## Test plan

Covered by `tests/Unit/SkosShaclValidatorTest.php` + `tests/Feature/SkosCrossMatchExportTest.php`:

- Clean taxonomy produces zero SHACL reports (baseline).
- S1 fires when a concept's prefLabel is empty.
- S2 fires when two prefLabels share a language.
- S3 fires when prefLabel and altLabel collide on literal+lang.
- S4 catches both linear cycles (a -> b -> c -> a) and self-cycles (a -> a).
- Cross-match round-trip: every match URI appears in all four serialisations.
- `?skos_xl=1` toggles `skosxl:` emission cleanly; absence keeps the legacy byte-for-byte output.

---

## Files touched

- `packages/ahg-term-taxonomy/database/install.sql` - `ahg_term_cross_match`
- `packages/ahg-term-taxonomy/src/Providers/AhgTermTaxonomyServiceProvider.php` - auto-install + register artisan
- `packages/ahg-term-taxonomy/src/Services/CrossMatchService.php` - new
- `packages/ahg-term-taxonomy/src/Validation/ShaclValidator.php` - new
- `packages/ahg-term-taxonomy/src/Console/SkosValidateCommand.php` - new
- `packages/ahg-term-taxonomy/src/Controllers/TermController.php` - exporter + edit/update extensions
- `packages/ahg-term-taxonomy/resources/views/edit.blade.php` - cross-match panel + JS row adder
- `packages/ahg-term-taxonomy/resources/shacl/skos-shapes.ttl` - vendored SKOS SHACL shapes
- `tests/Unit/SkosShaclValidatorTest.php` - rule coverage
- `tests/Feature/SkosCrossMatchExportTest.php` - exporter round-trip + XL toggle
- `docs/reference/skos-phase-3-matches-xl-shacl.md` - this file
- `docs/help/skos-validation.md` - in-app help article
