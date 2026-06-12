# Cite this record: bibliographic citation export

Summary: Heratio's `ahg-api` package exposes a "Cite this record" surface that emits standard bibliographic citation formats for one published archival record, so a researcher can import the reference into a reference manager. Read-only, published records only, CORS-open on the machine formats. No hardcoded host: every URL is built from `url()`. Lives in `packages/ahg-api` (not a locked path).

## Routes (packages/ahg-api/routes/api.php)

Registered at the ROOT (no group prefix), alongside `/id/{slug}`, `/sitemap.xml`, `/feed.atom`. Each runs under `throttle:120,1` + `api.cors`, with an `OPTIONS` preflight.

- `GET /cite/{idOrSlug}.bib`  (name `cite.bib`)  - BibTeX, `application/x-bibtex`
- `GET /cite/{idOrSlug}.ris`  (name `cite.ris`)  - RIS, `application/x-research-info-systems`
- `GET /cite/{idOrSlug}.json` (name `cite.csl`)  - CSL-JSON, `application/vnd.citationstyles.csl+json`
- `GET /cite/{idOrSlug}.dc.xml` (name `cite.dc`) - simple Dublin Core / OAI-DC, `application/xml`
- `GET /cite/{idOrSlug}`      (name `cite.show`) - themed HTML "Cite this" page (Bootstrap 5)

`{idOrSlug}` accepts a slug (grammar `[A-Za-z0-9][A-Za-z0-9\-_]*`) or a numeric `information_object` id.

## Resolution + published gate (REUSED, not reinvented)

`CitationController::resolve()` mirrors `EntityController::loadNode()`: slug (or numeric id) joined `slug` -> `information_object` -> `information_object_i18n` (culture), left-joined `status` on `type_id=158`; the record is returned only when `status_id=160` (Published); the synthetic root `id=1` is excluded; a schema variance yields `null`, never an exception. Creators come from the `event` table joined to `actor_i18n.authorized_form_of_name` (same as `EntityController::creators()`); the date is the event display date else a `start/end` span; the publisher is the holding repository's authorised name.

## Field mapping per format

| Field | Source | BibTeX | RIS | CSL-JSON | Dublin Core |
|---|---|---|---|---|---|
| Title | `information_object_i18n.title` | `title` | `TI` | `title` | `dc:title` |
| Author(s) | actors via `event` | `author` (`and`-joined) | `AU` (repeated) | `author` (`literal`) | `dc:creator` (repeated) |
| Year | 4-digit from date | `year` | `PY` | `issued.date-parts` | - |
| Date | event display / start-end | `note` | `DA` | `issued.raw` | `dc:date` |
| Publisher | holding repository | `publisher` + `howpublished` | `PB` | `publisher` + `archive` | `dc:publisher` |
| Identifier | `information_object.identifier` | `number` | `CN` | `call-number` | `dc:identifier` |
| URL | `url()` record page | `url` | `UR` | `URL` | `dc:identifier` |
| Item type | level of description | `@misc` (+`type=Collection`) | `TY  - GEN` | `manuscript`/`collection` | `dc:type` `Text`/`Collection` |

Honest: an absent field is omitted (no fabricated authors/dates).

## Escaping (no injection from titles/names)

- BibTeX: braced fields; `\\ { } $ & % # _ ~ ^` backslash-escaped (`bibtexEscape`); whitespace collapsed.
- RIS: CRLF-delimited `XX  - value` tags; newlines stripped from values so a title can't forge a tag.
- CSL-JSON: `json_encode` (always valid JSON; authors as `{literal}`, no given/family guessing).
- Dublin Core: `htmlspecialchars(..., ENT_QUOTES | ENT_XML1)` on every value.

## Catch-all safety

The single-segment `/{slug}` archival-record catch-all (constraint `[a-z0-9][a-z0-9-]*$`, one segment, no slash, no dot) can never capture a two-segment `/cite/...` path. The dotted format routes (`.bib`/`.ris`/`.json`/`.dc.xml`) are registered BEFORE the bare HTML route, so a suffix binds as a format, never as part of the slug. The HTML page is `/cite/{idOrSlug}` (two segments), not a bare `/cite`, so it too is catch-all-safe. A normal record slug still resolves to the catch-all (verified by route-list dispatch).

## Discovery

A `cite` surface entry was added to `ProtocolController::surfaces()` (between `entity` and `entity-actor`), so the capabilities document at `/open-data/protocol` advertises it with its `urlTemplate` and media types.

## Not-found behaviour

Unknown / unpublished / root record -> clean 404 in every format: a themed HTML 404 page, a BibTeX `%`-comment, an RIS empty `TY/ER` record, a CSL-JSON error object, or an empty OAI-DC document - never a 500, never a draft leak.

## Files

- `packages/ahg-api/src/Controllers/CitationController.php`
- `packages/ahg-api/resources/views/cite/show.blade.php`
- `packages/ahg-api/resources/views/cite/not-found.blade.php`
- `packages/ahg-api/routes/api.php` (route block + import)
- `packages/ahg-api/src/Controllers/ProtocolController.php` (`cite` surface)
