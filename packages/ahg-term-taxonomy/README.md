# ahg-term-taxonomy

Term + taxonomy browse and management for Heratio. Covers every enumerated controlled-vocabulary value used across the platform (subjects / places / genres / material-types / etc) plus full SKOS export with cross-vocab matches and pure-PHP SHACL validation.

## Install

Path-loaded via the root `composer.json`'s `packages/*` repository. Auto-registered by Laravel package discovery.

## Key surfaces

- `AhgTermTaxonomy\Services\TermBrowseService` - taxonomy browse used by /admin/taxonomies
- `AhgTermTaxonomy\Services\TermService` - term CRUD against the AtoM `term` + `term_i18n` base tables
- `AhgTermTaxonomy\Services\CrossMatchService` - SKOS cross-vocab links (exactMatch / closeMatch / broadMatch / narrowMatch / relatedMatch) via the `ahg_term_cross_match` table
- `AhgTermTaxonomy\Validation\ShaclValidator` - 4-rule pure-PHP SHACL validator over a SKOS taxonomy graph
- `AhgTermTaxonomy\Console\SkosValidateCommand` - `php artisan skos:validate` (validates one taxonomy against the four shipped shapes)
- `AhgTermTaxonomy\Controllers\TermController` - browse / edit / SKOS export endpoints

## SKOS export

`GET /term/export/skos/{format}` returns the taxonomy in RDF/XML, Turtle, N-Triples, or JSON-LD. Add `?skos_xl=1` for SKOS-XL emission (Phase 3, #661). Cross-vocab matches are included automatically when present.

## Routes

- `GET /admin/taxonomies` - taxonomy index
- `GET /term/{slug}` - term show
- `GET /term/{slug}/edit` - term edit
- `GET /term/export/skos/{format}` - SKOS export endpoint

## Database

Reads base AtoM `term` + `term_i18n`. Owns `ahg_term_cross_match` (cross-vocab matches), auto-installed via the service-provider Schema::hasTable probe.

## Related packages

- `ahg-metadata-export` - SKOS shapes used by other serialisers (DC qualified, MODS, RAD/DACS) flow back through term-taxonomy lookups
- `ahg-display` - the GLAM browse's subject / place / genre facets all resolve through term-taxonomy
