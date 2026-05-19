# Settings keys

All engine configuration lives in the `ahg_settings` table. Both codebases
read the same keys (Heratio via `AhgSettings::get()`, AtoM via
`QubitSetting::getByName()`).

Auto-seeded on first boot of `AhgAuthorityResolutionServiceProvider` (the
Heratio side) and on first run of `AhgAuthorityResolutionPlugin` (the
AtoM side). Manual override: edit the row in `/admin/settings` (Heratio)
or `/;settings/ahg` (AtoM).

## Core engine

| setting_key                                              | default                                                 | description                                                                                | Heratio | AtoM |
|---------------------------------------------------------|---------------------------------------------------------|--------------------------------------------------------------------------------------------|---------|------|
| `authority_resolution.candidate_top_n`                  | `5`                                                     | Top-N candidates persisted per mention in `ahg_mention_candidate`.                         | yes     | yes  |
| `authority_resolution.role_language_tokens`             | (see provider boot)                                     | JSON map of `kind => [token,...]` for context derivation (kinship, witness, location, ...).| yes     | yes  |
| `authority_resolution.decisions_graph_uri`              | `urn:heratio:auth-res:graph:decisions` / `urn:atom:...` | Fuseki named-graph URI for decision provenance (one URI per codebase).                     | yes     | yes  |
| `authority_resolution.field_provenance_graph_uri`       | `urn:heratio:auth-res:graph:field-provenance` / `urn:atom:...` | Fuseki named-graph URI for per-field authority-creation provenance.            | yes     | yes  |

## Lookup engine (Task 6)

All seven external authority sources share the same key shape:

```
lookup.<source>.enabled            (bool)   default off; flip to on per deployment
lookup.<source>.api_endpoint       (string) override the built-in default
lookup.<source>.rate_limit_per_min (int)    per-source rate limit
lookup.<source>.cache_ttl_seconds  (int)    per-source TTL in ahg_authority_lookup_cache
lookup.<source>.license_note       (string) attribution string shown on pre-fill
```

| source     | default endpoint                                              | default TTL | default rate limit |
|------------|---------------------------------------------------------------|-------------|--------------------|
| `viaf`     | `https://viaf.org/viaf/search`                                | 30 days     | 60/min             |
| `wikidata` | `https://www.wikidata.org/w/api.php`                          | 30 days     | 60/min             |
| `geonames` | `http://api.geonames.org/search`                              | 30 days     | 60/min             |
| `tgn`      | `http://vocabsservices.getty.edu/TGNService.asmx/TGNSearch`   | 90 days     | 30/min             |
| `gnd`      | `https://lobid.org/gnd/search`                                | 30 days     | 60/min             |
| `isni`     | `https://isni.oclc.org/sru/`                                  | 90 days     | 30/min             |
| `sagnc`    | (stub - no public API)                                        | 90 days     | 30/min             |

There is also a global precedence list:

| setting_key                                  | default                                              |
|---------------------------------------------|------------------------------------------------------|
| `lookup.precedence`                         | `["viaf","wikidata","geonames","tgn","gnd","isni","sagnc"]` |
| `lookup.http_timeout_seconds`               | `8`                                                  |

`PrefillEngine` walks the precedence list in order; the first source that
returns a hit wins for any given field.

## NER feedback (Task 9)

| setting_key                                        | default                          | description                                       |
|----------------------------------------------------|----------------------------------|---------------------------------------------------|
| `authority_resolution.ner_feedback.export_dir`     | `storage/app/auth-res/ner-feedback` | Where `auth-res:export-ner-feedback` drops JSONL. |
| `authority_resolution.ner_feedback.format`         | `jsonl`                          | Currently only `jsonl` is supported.              |

## Where they're read

| Layer                    | Code path (Heratio)                                         | Code path (AtoM)                                              |
|-------------------------|-------------------------------------------------------------|---------------------------------------------------------------|
| Boot auto-seed          | `AhgAuthorityResolutionServiceProvider::boot()`             | `ahgAuthorityResolutionPluginConfiguration::initialize()`     |
| Top-N read              | `CandidateGeneratorService::__construct`                    | `ahgCandidateGeneratorService::__construct`                   |
| Decisions graph URI     | `DecisionProvenanceWriter::DEFAULT_GRAPH_URI`               | `ahgDecisionProvenanceWriter::DEFAULT_GRAPH_URI`              |
| Lookup precedence       | `PrefillEngine::__construct`                                | `ahgPrefillEngine::__construct`                               |
| Role-language tokens    | `ContextDerivationService::loadTokens()`                    | `ahgContextDerivationService::loadTokens()`                   |

## Inspection

```bash
# Heratio side
sudo -u www-data php artisan tinker --execute='echo json_encode(\AhgCore\Models\AhgSettings::query()->where("setting_key","like","authority_resolution.%")->orWhere("setting_key","like","lookup.%")->orderBy("setting_key")->get(["setting_key","setting_value"]), JSON_PRETTY_PRINT);'

# AtoM side
php symfony ahg:settings:list --prefix=authority_resolution
php symfony ahg:settings:list --prefix=lookup
```
