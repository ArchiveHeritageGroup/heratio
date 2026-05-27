# BIBFRAME Implementation Reference

**Issue:** heratio#760
**Package:** `packages/ahg-biblio-bf/`
**Status:** Conversion + serialisation + agent-form editing shipped. Full graph editor + ODI conformance dossier pending.

## Package surface

| Component | Path |
|---|---|
| Service provider | `src/Providers/AhgBiblioBfServiceProvider.php` |
| Controller | `src/Controllers/BibframeController.php` (233 lines) + `BiblioBfController.php` |
| Service | `src/Services/BibframeService.php` (613 lines) |
| Serializer | `src/Services/BibframeSerializer.php` (431 lines) |
| Routes | `routes/web.php` |
| Config | `config/ahg-biblio-bf.php` |
| Views | `resources/views/{index,import,export,validate,agent}.blade.php` |

Discovered by Laravel via `extra.laravel.providers` in `packages/ahg-biblio-bf/composer.json`. Required as `ahg/biblio-bf: @dev`.

## Routes

```
GET  /bibframe                 -> bibframe.index
GET  /bibframe/import          -> bibframe.import
POST /bibframe/import          -> bibframe.import-run
GET  /bibframe/export          -> bibframe.export
POST /bibframe/export          -> bibframe.export-run
GET  /bibframe/validate        -> bibframe.validate
POST /bibframe/validate        -> bibframe.validate-run
GET  /bibframe/agent           -> bibframe.agent
GET  /bibframe/{workId}        -> bibframe.show
```

## Vocabulary

- `bf:` -> https://id.loc.gov/ontologies/bibframe/
- `bflc:` -> https://id.loc.gov/ontologies/bflc/
- `rdf:` / `rdfs:` / `xsd:` standard W3C
- `heratio:` -> configurable, defaults to `https://heratio.example/bf/`

## MARC <-> BIBFRAME mapping

`BibframeService::marcToBibframe(array $marcRecord): array` and the inverse `bibframeToMarc(array $rdf): array`. The mapping is field-level (245, 100, 020, 650 etc.) and follows LoC `marc2bibframe2` semantics. Round-trip preserves all controlled-vocabulary annotations.

## Serialisation

`BibframeSerializer::serialize(array $graph, string $format): string` where `$format` ∈ `rdfxml`, `turtle`, `jsonld`.

## Gaps vs heratio#760 acceptance

- Full graph-aware BIBFRAME Editor UI (agent view ships today; full editor remaining)
- External NISO ODI Working Group review (self-attested dossier published at `docs/reference/bibframe-odi-conformance.md` v1.112+; external review is the remaining step)
- Help article ingestion into in-app /help (markdown shipped at `docs/help/bibframe-user-guide.md`)
