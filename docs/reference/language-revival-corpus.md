# Language-revival corpus (heratio#1208 North Star slice)

A public, read-only language-revival surface in the free `ahg-semantic-search`
package (NOT locked). It turns the collection into a living resource for heritage and
endangered languages, alongside (and untouched by) the existing corpus-grounded
"ask-the-collection" feature which lives in `ahg-core`
(`AskCollectionService` / `AskCollectionController`, KM-backed).

## Where it lives

Package: `packages/ahg-semantic-search` (free; hosts ask-the-collection's siblings:
discoveries, displaced-heritage, at-risk register). The North Star epic #1208 stays
OPEN; this is one slice.

- Service: `src/Services/LanguageCorpusService.php`
- Controller: `src/Controllers/LanguageCorpusController.php`
- Views: `resources/views/language-corpus/{index,show,glossary-moderate}.blade.php`
- Table install: `database/install_language_glossary.sql`
- Routes: public in the provider `register()`; admin in `routes/web.php`.

## Read-only corpus sources (no writes to existing tables)

For a chosen culture (base subtag; regional variants like `pt_BR`, `de-CH` collapse
onto the base):

- **Records described in it** - `information_object_i18n` joined to the published-id
  subquery (`status.type_id=158 AND status_id=160 AND object_id>1`), real non-blank
  title, matched on `LOWER(base(culture))`.
- **Words** - `term_i18n` joined `term`, `taxonomy_id IN (42 Places, 35 Subjects)`,
  split into place-names vs subject terms.
- **Transcriptions / full text** - in-culture `information_object_i18n.scope_and_content`
  of meaningful length, published only.
- **Approved glossary** - `language_revival_glossary` where `moderation_status='approved'`.

The published gate and the af-before-nl ordering mirror the core
`LanguageCoverageService`; the language-label map is copied from it.

## The one new table: `language_revival_glossary`

Community-contributed glossary. Columns: `culture` (VARCHAR soft code), `term`,
`meaning`, `usage_example`, `source`, `moderation_status` (VARCHAR, never ENUM:
pending/approved/rejected), `contributed_by` (soft user id, no FK), `contributor_name`,
`moderated_by`, `moderated_at`, timestamps. `CREATE TABLE IF NOT EXISTS`, auto-installed
on first boot via `bootLanguageGlossaryTable()` (SQL-file-preferred, Schema-builder
fallback, single try/catch - never fatals a fresh boot, per the CI hasTable rule). No
foreign keys, no ALTER of any existing table.

## Moderation

New contributions land `pending` and are never shown publicly. Admins (auth + admin)
approve/reject at `/language-corpus-admin/glossary`.

## Optional gateway translation + MT label

`LanguageCorpusService::translateSnippet()` calls `\AhgAiServices\Services\LlmService::translate()`
only via `app()` + `class_exists` guard (the AI package is locked; we reach it solely
through its public abstraction, never a node port). LlmService already routes through
`ai.theahg.co.za/ai/v1`. Every translated result is presented with the standing label:
"Machine translation via the AHG gateway - not an official or authoritative translation."
Soft-fails to "unavailable" (HTTP 200 JSON), original text intact. No bulk MT of any
SA/heritage language.

## Routes + catch-all safety

Public single-segment `/language-corpus` would otherwise be eaten by the locked
`/{slug}` archival-record catch-all in `ahg-information-object-manage`. It is bound in
the provider `register()` via `callAfterResolving('router')` so it binds before any
boot() (the established pattern for `/discoveries`, `/displaced-heritage`, `/at-risk`;
see `reference_slug_catchall_route_precedence.md`). The per-language `/language-corpus/{culture}`,
its `contribute` and `translate` POSTs are bound the same way with a culture-code
`where()` constraint. Admin moderation routes are under `/language-corpus-admin/...`
(2-segment+, no collision). No edit to the locked IO package was needed.

## Guarantees

Read-only over the catalogue; only `language_revival_glossary` is written; no ALTER;
no locked path touched; ask-the-collection (ahg-core) untouched. Every screen has an
empty-state and degrades rather than 500ing. Bootstrap 5 + `theme::layouts.1col`.
International, Afrikaans first-class.
