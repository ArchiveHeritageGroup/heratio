# ahgTermTaxonomyPlugin — Terms, Taxonomies & SKOS

The **ahgTermTaxonomyPlugin** is Heratio's controlled-vocabulary surface. Subjects, places, genres, levels of description, media types, accession statuses, condition grades — anything an archivist would otherwise type as free text — lives here as a **term** within a **taxonomy**.

Controlled vocabularies make the catalogue searchable, faceted, and machine-readable. They are exported as **SKOS** (Simple Knowledge Organization System) for cross-institutional interoperability.

---

## Concepts

| Concept | Detail |
| --- | --- |
| **Taxonomy** | A named container for a set of terms. Each taxonomy has an `id` (often referenced from code by an `ahgTaxonomy::FOO_ID` constant) and lives in the `taxonomy` table. |
| **Term** | One vocabulary entry. Every term belongs to exactly one taxonomy and may have a parent term (forming a hierarchy). Terms live in the `term` table; localised labels in `term_i18n`. |
| **Hierarchy** | Terms within a taxonomy use MPTT (`lft`/`rgt`) so a single range scan returns a parent and all its descendants. |
| **Use-for / variants** | Alternative labels (synonyms / lexical variants) live in `other_name` linked to the term. They surface in autocomplete but resolve to the canonical term. |
| **Relations** | Cross-references between terms (broader, narrower, related) live in `term_relation`. |

---

## Where it lives

| Surface | URL |
| --- | --- |
| Taxonomy index | `/taxonomy/list` |
| Browse taxonomy terms | `/taxonomy/<slug>` |
| Term browse | `/term/browse?taxonomy=<id>` |
| Term show | `/term/<slug>` |
| Add term | `/term/add?taxonomy=<id>` |
| Edit term | `/term/<slug>/edit` |
| Move term | `/term/<slug>/move` |
| Merge two terms | `/admin/term/merge` |
| Autocomplete | `/term/autocomplete?taxonomy=<id>` |
| SKOS export | `/taxonomy/<slug>.rdf` (`Accept: application/rdf+xml`) |
| Dropdown manager (enumerated values) | `/admin/dropdowns` |

---

## Built-in taxonomies

These ship with Heratio and are referenced by code. **Don't delete them.**

| ID | Name | Used by |
| --- | --- | --- |
| 34 | Level of description | `information_object.level_of_description_id` |
| 35 | Subject | IO subject access points |
| 42 | Place | IO place access points |
| 78 | Genre | IO genre access points |
| 32 | Actor entity type | `actor.entity_type_id` |
| 65 | Repository type | `repository.repository_type_id` |
| 99 | Media type | `digital_object.media_type_id` |
| 158 | Publication status | publication state of every object |

The plugin's own seed file (`packages/ahg-term-taxonomy/database/install.sql`) creates these on first boot.

---

## Common workflows

### Add a new subject term

1. `/taxonomy/list` → click **Subject**.
2. Click **Add new term**.
3. Fill **Preferred label** (and any **Use-for** variants).
4. Optional: pick a parent (broader term) for hierarchy.
5. Save. The term gets a slug (`world-war-ii`) and is immediately available in IO subject autocomplete.

### Bulk import a taxonomy from SKOS

1. `/taxonomy/import-skos` (admin only).
2. Upload `.rdf` / `.ttl` file. The plugin parses `skos:Concept` nodes, maps `skos:prefLabel` → preferred label, `skos:altLabel` → use-for, `skos:broader` → parent.
3. Preview first 50 mappings; commit. Background job runs the load.

### Merge duplicates

Two terms drift in (e.g. "World War II" and "Second World War"):

1. `/admin/term/merge`.
2. Pick **survivor** + **doomed** terms.
3. Review every IO link, every relation, every i18n row.
4. Confirm — doomed term references are repointed to the survivor; doomed term row is deleted.

> Audit-logged when `ahgAuditTrailPlugin` is enabled.

### Move a term to a new parent

1. Open the term show page.
2. **Move** action (top-right).
3. Choose new parent. The MPTT range of the term and all descendants is recalculated.

---

## Dropdown Manager vs Term Taxonomy

There are two enumerated-value systems in Heratio. Use the right one:

| | **Term Taxonomy** | **Dropdown Manager** |
| --- | --- | --- |
| Where | `term`/`taxonomy` table | `ahg_dropdown` table |
| Use for | **Descriptive vocabulary** with hierarchy, multilingual, SKOS-export — places, subjects, genres, actor types | **Configuration enums** — internal codes, statuses, simple flat lists with no need for SKOS |
| UI | `/taxonomy/list`, `/term/<slug>` | `/admin/dropdowns` |
| Hierarchy? | Yes (MPTT) | Flat |
| Multilingual? | Yes (`term_i18n`) | Single-locale labels |
| SKOS export? | Yes | No |

Rule of thumb: **if a researcher might want to search/browse by it, it's a taxonomy term**. If it's an internal status or a never-translated config option, it's a dropdown.

---

## Settings

The plugin has no top-level settings page; behaviour is configured per taxonomy:

- **Taxonomy edit form** (`/taxonomy/<slug>/edit`):
  - **Use as facet on browse** — adds the taxonomy to the GLAM browse facet sidebar.
  - **Allow multiple values per record** — single vs many on the IO edit form.
  - **Source authority** — VIAF / Getty TGN / Library of Congress — drives external lookup buttons on term add.
- **Site-wide settings:** none — but `/admin/settings/ahg/authority` controls the merge auto-pre-select threshold (string similarity) used by the term merge tool too.

---

## Permissions

| Action | Required role |
| --- | --- |
| Browse, view | Anonymous |
| Add term | Editor (`acl:create`) |
| Edit term | Editor (`acl:update`) |
| Move term | Editor |
| Merge terms | Admin |
| Delete term | Admin (and refused if the term is in use) |
| Import SKOS | Admin |
| Edit a built-in taxonomy's name/id | Admin (and discouraged — code references the id) |

---

## SKOS export

Each taxonomy can be retrieved as RDF/XML or Turtle:

```
GET /taxonomy/places.rdf
GET /taxonomy/places.ttl    Accept: text/turtle
```

The export emits `skos:ConceptScheme` for the taxonomy, `skos:Concept` per term, `skos:prefLabel` per culture, `skos:broader`/`skos:narrower` per hierarchy edge.

Useful for handing the institution's controlled vocabulary to a partner or to a discovery layer that aggregates multiple archives.

---

## Common gotchas

- **Don't delete a term that is in use.** The delete refuses if any object_term link or term_relation references it. Reassign first.
- **Slug stability.** Renaming a term's preferred label does *not* rename its slug. Use **Rename slug** explicitly if you need the URL to change — and remember the old URL will 404 unless an admin adds a redirect.
- **Built-in taxonomy IDs are referenced from code.** `taxonomy.id = 35` is `Subject`. Don't reassign or delete the row — code paths break.
- **Source culture.** A term's `source_culture` is the language it was originally created in. If no translation exists, the show page falls back to the source-culture label.
- **MPTT integrity.** Manual SQL `DELETE`/`UPDATE` on the `term` table will corrupt the lft/rgt range — go through the controller, or run `php artisan ahg:nested-set-rebuild --table=term` after.
- **Facet counts cache.** Adding a term or moving one in a faceted taxonomy doesn't immediately update the GLAM browse facet counts — they're cached. Run `php artisan ahg:display-reindex --facets-only` (or wait for the nightly rebuild).

---

## Related

- **`ahgInformationObjectManagePlugin`** — the records that get tagged with these terms.
- **`ahgActorManagePlugin`** — uses entity-type and occupation taxonomies.
- **`ahgRicExplorerPlugin`** — RiC graph view of concept relationships.
- **`ahgAIPlugin`** — NER pipeline that suggests new subject terms from IO content.
- **Help articles**: *Multilingual Cataloguing*, *AHG Authority Records — User Guide*
