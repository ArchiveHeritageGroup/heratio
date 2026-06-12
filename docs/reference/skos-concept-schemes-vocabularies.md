# Controlled vocabularies as SKOS concept schemes (/vocabularies, /vocabulary)

Heratio publishes its controlled vocabularies (authorities) as standard SKOS
concept schemes, the conventional linked-data way to expose an institution's
authorities. Three schemes ship from the term taxonomies: subjects, places and
genres / forms. Read-only, open data, CORS-open on the RDF/JSON forms. Built in
`packages/ahg-api` (not a locked package). North-star #1204 (the open memory
protocol).

## Surfaces

- `GET /vocabularies` - HTML index of the published concept schemes with live
  term counts and per-scheme links. `?format=json` (or a JSON Accept) returns a
  machine list (a schema.org DataCatalog of skos:ConceptScheme entries).
- `GET /vocabulary/{taxonomy}` (+ `.ttl` / `.jsonld` / `.rdf`) - ONE taxonomy as
  a `skos:ConceptScheme`. Content-negotiated; the bare path defaults to JSON-LD
  (machine-first). Each term becomes a `skos:Concept`.
- `GET /vocabulary/{taxonomy}/{termId}` (+ `.ttl` / `.jsonld` / `.rdf`) - ONE
  concept nested under its scheme, with a bounded handful of example published
  records (`dcterms:relation` -> `/id/{slug}` record URIs).

`{taxonomy}` is a safe, fixed slug (`subjects` | `places` | `genres`) mapped to a
term taxonomy id, so it can never collide with a numeric id. `{termId}` is
numeric. The canonical per-term URI remains `/id/term/{slug}`
(TermEntityController); each concept here carries `skos:exactMatch` to it.

## SKOS structure emitted

The ConceptScheme node carries `skos:prefLabel`, a description, and
`skos:hasTopConcept` for each top concept. Each Concept node carries:

- `skos:prefLabel` - the term label from `term_i18n.name`, in the active culture.
- `skos:notation` - the term `code` when present, else the stable numeric id.
- `skos:inScheme` - the scheme URI.
- `skos:topConceptOf` - when the term's parent is the taxonomy root (a top
  concept), or `skos:broader` - when the parent is itself a concept.
- `skos:narrower` - children that are concepts in the same scheme.
- `skos:scopeNote` - from `note` / `note_i18n` where `note.type_id = 122`
  (scope note), in the active culture, when present.
- `skos:exactMatch` - the canonical `/id/term/{slug}` URI.
- `dcterms:relation` (concept detail only) - bounded example published records.

Namespaces: `skos:` `http://www.w3.org/2004/02/skos/core#`, `dcterms:`
`http://purl.org/dc/terms/`, plus the shared `rdf:` / `rdfs:` / `schema:`.

## Data model used (DESCRIBE-verified)

- `term` columns: `id, taxonomy_id, code, parent_id, lft, rgt, source_culture`.
- `term_i18n` columns: `name, id, culture` (the label; no scope note here).
- Scope notes live in `note` (`object_id, type_id, ...`) + `note_i18n`
  (`content, id, culture`); the scope-note type id is `122`.
- Taxonomy ids: subjects = 35 (112 terms), places = 42 (181 terms), genres = 78
  (53 terms). The shared taxonomy-root term is id `110` (its `parent_id IS NULL`);
  the published terms are parented at it, so they are top concepts of the scheme.
  Hierarchy is computed generically: a term's parent is a `skos:broader` only when
  the parent is itself a concept of the scheme, otherwise the term is a top
  concept. Both `parent_id` and `lft`/`rgt` are available; ordering uses
  `COALESCE(lft, id)`.

Nothing is hardcoded about the vocabulary content: labels, notations, hierarchy
and scope notes all come from the data, language-tagged by culture. A fresh
install with different authorities self-describes from its own term tables.

## Implementation

- Controller: `packages/ahg-api/src/Controllers/VocabularyController.php`.
- RDF rendering reuses `AhgApi\Services\GraphSerializerService` (the single
  source of truth for namespaces + Turtle/RDF-XML escaping, shared by every
  entity and graph surface). No new RDF library was added. The shared `@context`
  gained the SKOS scheme predicates (`inScheme`, `topConceptOf`, `hasTopConcept`,
  `notation`, `scopeNote`, `note`, `exactMatch`) so all three serialisations
  (JSON-LD, Turtle, RDF/XML) render the same predicates and can never drift.
- Routes (`packages/ahg-api/routes/api.php`): dotted-suffix routes registered
  before the bare ones so a `.ttl`/`.jsonld`/`.rdf` suffix binds as a format;
  `{taxonomy}` constrained to a lowercase slug grammar, `{termId}` to digits.
- `ProtocolController::surfaces()` declares two new surfaces: `vocabularies` and
  `vocabulary`, so the SKOS schemes appear in the open-memory-protocol index.

## Catch-all safety

- `/vocabulary/{taxonomy}` and `/vocabulary/{taxonomy}/{termId}` are
  multi-segment, and the dotted variants carry a `.`, so the single-segment
  `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*`, one
  segment, no slash, no dot) can never capture them.
- `/vocabularies` is single-segment; `ahg-api` is discovered before
  `ahg-information-object-manage` (alphabetical package order), so it registers
  first and wins the match. Verified: `/vocabularies`, `/vocabulary/subjects`,
  `/vocabulary/subjects.ttl`, `/vocabulary/places/901113(.jsonld)` all bind to the
  vocabulary controller, while normal record slugs (`/durban`, `/some-fonds-2024`,
  `/vocabularies-but-longer`) still fall through to the catch-all.

## Bounded + honest

A scheme dump is capped (1000 concepts) with an honest `skos:note` on the scheme
when the taxonomy exceeds the cap, pointing the consumer at the per-concept URIs.
Narrower lists and example records are capped too. An unknown scheme slug or term
id yields a clean negotiated 404 (Turtle / RDF-XML / JSON-LD), never a 500; every
enrichment query is guarded with `Schema::hasTable` + try/catch.

## Safety

Read-only end to end: no DB writes, no ALTER, no new table. The example-record
list is filtered through the published-only gate (`status.type_id = 158`,
`status_id = 160`; root id 1 excluded), so a draft record is never leaked through
a concept. Every URI is built from `url()`, never a hardcoded host, so a relocated
install self-resolves.

## Internationalisation

Jurisdiction-neutral, standards-based (SKOS / Dublin Core), no market
assumptions. Labels and scope notes are language-tagged by the active culture, so
the same scheme serves any locale's authorities.
