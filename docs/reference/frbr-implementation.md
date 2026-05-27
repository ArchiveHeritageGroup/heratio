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

## Work-key engine (v1.112+)

| Component | Path |
|---|---|
| Generator + clustering helper | `src/Services/WorkKeyService.php` |
| Override admin controller | `src/Controllers/WorkOverrideController.php` |
| Backfill command | `src/Console/Commands/FrbrBackfillWorkKeysCommand.php` (`ahg:frbr-backfill-work-keys`) |
| Migration: `library_item.work_key` | `database/migrations/2026_05_27_010000_add_work_key_to_library_item.php` |
| Migration: `library_work_override` table | `database/migrations/2026_05_27_020000_create_library_work_override_table.php` |

Override admin routes:
```
GET    /admin/frbr/overrides
GET    /admin/frbr/overrides/create
POST   /admin/frbr/overrides
DELETE /admin/frbr/overrides/{id}
POST   /admin/frbr/cluster (preview siblings)
```

## Gaps vs heratio#763 acceptance

- Wire `WorkKeyService::clusterItems()` into the GLAM browse result-set renderer (lives in locked `ahg-display`)
- ES `_doc` mapping update to include `work_key` (current implementation queries DB-side; ES integration is the remaining performance lever)
- Full 132k-record benchmark publication (initial 14-row local timing sub-millisecond)
- Help article ingestion into in-app /help (markdown shipped)
