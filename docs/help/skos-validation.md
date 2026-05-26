# SKOS Validation

Heratio can check every taxonomy term against the SKOS data model to catch incomplete or inconsistent records before they reach an exporter, RDF endpoint, or downstream system.

## What gets checked

Four rules from the SKOS Reference (W3C) are enforced today:

1. **Every term must have a label.** A `skos:Concept` without any `skos:prefLabel` is invalid.
2. **One preferred label per language.** A term may have many translations, but only one preferred form per language.
3. **Preferred and alternative labels must differ.** The same literal cannot appear as both `prefLabel` and `altLabel` in the same language.
4. **No circular hierarchies.** Following `broader` (parent) links must never loop back to the starting term.

Running the validator gives a per-violation report with the shape id (S1..S4), the term URI, and a human-readable message. The artisan command exits non-zero when violations are found, so you can wire it into CI / pre-deploy hooks.

## How to run it

```bash
# Validate a single taxonomy by id
php artisan skos:validate --taxonomy=35

# Validate every taxonomy in the system
php artisan skos:validate

# Emit JSON instead of human-readable lines (useful for CI parsing)
php artisan skos:validate --json
```

Example output:

```
Found 2 SHACL violation(s):
  [S1-MinPrefLabel] taxonomy=35 concept=https://example.org/term/47 : Concept has no skos:prefLabel.
  [S4-NoBroaderCycles] taxonomy=35 concept=https://example.org/term/142 : Concept participates in a skos:broader transitive cycle.
```

## Cross-vocabulary matches

When you edit a term (`/term/{slug}/edit`) you now get a **Cross-vocabulary matches** panel. Use it to link the Heratio term to the same (or similar) concept in another controlled vocabulary - LCSH, Getty AAT, Wikidata, MeSH, etc. Pick one of the five SKOS mapping predicates:

- **exactMatch** - the same concept in another vocabulary
- **closeMatch** - close but not interchangeable
- **broadMatch** - the external concept is broader than yours
- **narrowMatch** - the external concept is narrower than yours
- **relatedMatch** - related, but not in the same hierarchy

These appear on every SKOS export of the taxonomy across all four serialisations (RDF/XML, Turtle, N-Triples, JSON-LD).

## SKOS-XL labels

The export endpoint accepts a `?skos_xl=1` flag that switches on **SKOS-XL** label emission alongside the regular plain-text labels. SKOS-XL turns each label into a first-class resource with its own URI and provenance, which is useful for downstream consumers that need to attach history or attribution to individual labels.

```
GET /term/export/skos/turtle?taxonomy=35&skos_xl=1
```

The plain `skos:prefLabel "Archives"@en` triples are always emitted; XL is purely additive.

## Background

Implementation lives in `packages/ahg-term-taxonomy/`. The vendored SKOS SHACL shapes are at `packages/ahg-term-taxonomy/resources/shacl/skos-shapes.ttl`. See `docs/reference/skos-phase-3-matches-xl-shacl.md` for the engineering details.
