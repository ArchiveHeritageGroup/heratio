# ahgActorManagePlugin — Authority Records

The **ahgActorManagePlugin** is Heratio's surface for *authority records* — the people, families, and corporate bodies that created, donated, or are otherwise associated with archival material. They are described according to **ISAAR(CPF)** (International Standard Archival Authority Record — Corporate bodies, Persons, Families).

Authority records are kept separately from archival descriptions so the same person/body can be referenced by many records without duplication, and so authority data (biographies, dates, mandates) can be edited in one place.

---

## What it does

| Capability | Detail |
| --- | --- |
| Browse | `/actor/browse` — paged, faceted, filterable by entity type (person / family / corporate body) and by repository |
| Show | `/actor/<slug>` — full ISAAR(CPF) detail page with the record's history, dates, mandates, related records, and i18n variants |
| Add | `/actor/add` — create a new authority record (any logged-in editor) |
| Edit | `/actor/<slug>/edit` — update fields, including authorized form of name, parallel forms, dates, places, mandates, history |
| Merge | `/admin/actor/merge` — collapse two duplicate authority records into one, preserving every link |
| Autocomplete | `/actor/autocomplete` — JSON endpoint used by the IO edit page when adding a creator |
| External linking | VIAF, Wikidata, GeoNames lookups via `/admin/ric/lookup-external` (requires `ahgRicExplorerPlugin`) |

---

## ISAAR(CPF) field set

The edit form mirrors ISAAR(CPF) exactly — every field maps to a column in `actor` or `actor_i18n`:

- **1.1 Identity area** — entity type, authorized form of name, parallel forms, standardised forms, other forms, identifiers
- **1.2 Description area** — dates of existence, history, places, legal status, functions/occupations/activities, mandates, internal structures, general context
- **1.3 Relationships area** — links to other actors (employer, member-of, parent-of, etc.) — uses the generic `relation` table
- **1.4 Control area** — authority-record identifier, institution responsible, rules/conventions, status (draft/published), revision history, sources

Multilingual: every i18n field has per-culture rows in `actor_i18n`. Switching the site locale switches what's displayed.

---

## Common workflows

### Adding a creator while cataloguing

The most common path: you're editing an information object and need to attribute it to a person who isn't in the system yet.

1. Open the IO edit page (`/<slug>/edit`).
2. In the **Creators** section, type the creator's name. The autocomplete polls `/actor/autocomplete`.
3. If no match, click **Create new** — a modal opens with the minimal "authorized form of name + entity type" fields.
4. Save — the actor row is created and immediately linked back to the IO via the `event` table (creator event).
5. Open `/actor/<new-slug>` later to flesh out the biography, dates, mandates, etc.

### Merging duplicates

Authority data accumulates duplicates over time — typo variants, naming conventions ("Smith, J." vs "Smith, John"), or legacy import artefacts.

1. Open `/admin/actor/merge`.
2. Pick the **survivor** (the canonical record) and one or more **doomed** duplicates.
3. Review the merge preview — every information object link, every relation, every i18n string is shown.
4. Confirm. The doomed records' object_ids are repointed to the survivor's id and the doomed actor rows are deleted.

> **Audit trail**: every merge writes a row in `ahg_audit_log` with the from/to ids and the editor's user id (when `ahgAuditTrailPlugin` is enabled).

### Bulk import from CSV

For migrations, you can ingest authority records from CSV via the **AtoM Authority Records** template at `/researcher/import/csv` or `/informationobject/import/csv` (the wizard auto-detects the entity type from the column set).

---

## Settings (`/admin/settings/ahg/authority`)

- **Completeness scoring** — calculates a 0-100 score from how many ISAAR(CPF) fields are filled. Drives the per-record completeness badge on browse.
- **NER pipeline** — when enabled and `ahgAIPlugin` is on, every IO save runs a name-entity recognition pass and offers to link extracted person/org names to existing authority records (or create new ones).
- **Auto-merge threshold** — string-similarity score (default 0.95) above which the merge tool auto-pre-selects "this looks like the same record".
- **External lookup default** — VIAF / Wikidata / GeoNames — primary source for the `/admin/ric/lookup-external` widget.

---

## Permissions

| Action | Required role |
| --- | --- |
| Browse, view | Anonymous (public records only) / authenticated (all) |
| Add, edit | Editor (`acl:create`, `acl:update`) |
| Merge | Administrator |
| Delete | Administrator |
| Bulk import | Administrator |

ACL groups are configured at `/aclGroup`; per-record clearance overrides are at `/admin/security-clearance`.

---

## Common gotchas

- **Source culture vs current locale** — `actor.source_culture` is the language the authority record was *originally* written in. The site shows the user's current locale, falling back to `source_culture` if no translation exists. Edits under the locale `en` create an `actor_i18n` row with `culture='en'` — they don't overwrite the source if it's a different language.
- **Class-table inheritance** — `actor` is the parent of `repository`, `donor`, `rights_holder`, and `user`. Deleting an actor row through the UI cascades to the subclass row; merging only collapses peer-class duplicates (you can't merge a person into a repository).
- **Slugs aren't auto-regenerated** — renaming the authorized form of name does **not** rename the URL. To rename the slug too, use `/actor/<slug>/rename` (admins only).

---

## Related

- **ahgActorCompletenessPlugin** — adds the per-actor completeness widget on browse and show
- **ahgAuthorityPlugin** — extends with NER linking, external authority resolution, and occupation/function browse
- **ahgRicExplorerPlugin** — RiC graph view of an actor's relationships
- **ahgAuditTrailPlugin** — change-history log
- **Help articles**: *AHG Authority Records — User Guide* (deeper dive into ISAAR(CPF) field semantics)
