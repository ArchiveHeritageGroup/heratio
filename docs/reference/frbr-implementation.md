# FRBR Implementation Reference

**Issue:** heratio#763
**Package:** `packages/ahg-biblio-frbr/`
**Status:** Serialisation surface (import/export/validate/agent) shipped. Work-key generator + ES clustering + force-group/split + 132k benchmark pending.

## Package surface

| Component | Path |
|---|---|
| Service provider | `src/Providers/AhgBiblioFrbrServiceProvider.php` |
| Controller | `src/Controllers/FrbrController.php` (213 lines) |
| Service | `src/Services/FrbrService.php` (597 lines) |
| Routes | `routes/web.php` |
| Config | `config/ahg-biblio-frbr.php` |
| Views | `resources/views/{index,import,export,validate,agent}.blade.php` |

Discovered via `extra.laravel.providers`; required as `ahg/biblio-frbr: @dev`.

## Routes

```
GET  /frbr                  -> frbr.index
GET  /frbr/import           -> frbr.import
POST /frbr/import           -> frbr.import-run
GET  /frbr/export           -> frbr.export
POST /frbr/export           -> frbr.export-run
GET  /frbr/validate         -> frbr.validate
POST /frbr/validate         -> frbr.validate-run
GET  /frbr/agent            -> frbr.agent
GET  /frbr/{workId}         -> frbr.show
```

## Vocabulary

- `frbr:` -> http://iflastandards.info/ns/fr/frbr/frbrer/
- `dc:` / `dcterms:` standard
- IFLA Library Reference Model (LRM) is the successor; mapped equivalents are emitted as `lrm:` predicates where relevant.

## Work-key recipe (planned)

```php
$key = hash('xxh64',
    normaliseTitle($io->title) . '|' .
    normaliseCreator($io->primaryCreator())
);
```

- NFD-normalise; lowercase; strip diacritics + non-alphanumeric.
- Uniform title (MARC 130/240) takes precedence over 245 when present.
- `library_work_override` table provides force-group / force-split escape hatches.

## Gaps vs heratio#763 acceptance

- [ ] Work-key column on `library_item` and ES doc
- [ ] ES query-time clustering on work-key
- [ ] Search-result "View all manifestations" UI affordance
- [ ] Cataloguer admin for force-group / force-split
- [ ] 132k-record benchmark <500ms
- [ ] Help article inside in-app /help (only the operator reference is shipped today)
