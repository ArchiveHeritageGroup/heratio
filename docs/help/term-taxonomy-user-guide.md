> Heratio Help Center article. Category: User Guide.

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
| Edit term (incl. re-parent / move) | `/term/<slug>/edit` |
| Autocomplete | `/term/autocomplete?taxonomy=<id>` |
| SKOS export | `/term/export/skos/rdf-xml` (also `/turtle`, `/ntriples`, `/jsonld`) |
| SKOS import | `/term/import/skos` (admin only) |
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

1. `/term/import/skos` (admin only).
2. Upload `.rdf` / `.ttl` file. The plugin parses `skos:Concept` nodes, maps `skos:prefLabel` → preferred label, `skos:altLabel` → use-for, `skos:broader` → parent.
3. Preview first 50 mappings; commit. Background job runs the load.

### De-duplicate terms

Two terms drift in (e.g. "World War II" and "Second World War"). Term-taxonomy has no one-click merge route; reconcile manually:

1. Decide which term survives.
2. Re-tag any records still pointing at the doomed term to the survivor.
3. Delete the now-unused term (`/term/<slug>/delete`). The delete refuses while any object link or relation still references it — see *Common gotchas*.

> Authority-record (actor) de-duplication **does** have a dedicated merge tool (`/admin/dedupe/merge/<id>`); that is a separate surface from taxonomy terms.

### Move a term to a new parent

1. Open the term edit form (`/term/<slug>/edit`).
2. Change the **parent term** field.
3. Save. The MPTT range of the term and all descendants is recalculated.

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
- **Site-wide settings:** none.

---

## Permissions

| Action | Required role |
| --- | --- |
| Browse, view | Anonymous |
| Add term | Editor (`acl:create`) |
| Edit term | Editor (`acl:update`) |
| Move term (re-parent via edit) | Editor (`acl:update`) |
| Delete term | Admin (and refused if the term is in use) |
| Import SKOS | Admin |
| Edit a built-in taxonomy's name/id | Admin (and discouraged — code references the id) |

---

## SKOS export

Each taxonomy can be retrieved as RDF/XML or Turtle:

```
GET /term/export/skos/rdf-xml
GET /term/export/skos/turtle
GET /term/export/skos/ntriples
GET /term/export/skos/jsonld
```

The export emits `skos:ConceptScheme` for the taxonomy, `skos:Concept` per term, `skos:prefLabel` per culture, `skos:broader`/`skos:narrower` per hierarchy edge.

Useful for handing the institution's controlled vocabulary to a partner or to a discovery layer that aggregates multiple archives.

---

## Community protocols (African taxonomies)

Some vocabulary terms carry an **Indigenous / community access protocol** - a Traditional Knowledge (TK) or Biocultural (BC) label plus an access condition that governs who may see the term and the records tagged with it. This lets a community assert governance over its own knowledge (the "term-plus-protocol-plus-owner" model from the African-taxonomies governance work, issue #1388), grounded in the CARE principles and Local Contexts labelling.

### Setting a protocol

On the **term add / edit form** (`/term/<slug>/edit`) a **Community protocol** fieldset carries:

| Field | Meaning |
| --- | --- |
| **Access condition** | How the term is disclosed - see the table below. |
| **Label family** | `TK` (Traditional Knowledge) or `BC` (Biocultural). |
| **Label code** | The specific label, e.g. `tk_attribution`, `tk_secret`. |
| **Region module** | The community-governance region, e.g. `southern_africa`. |
| **Owner** | The authority record (community) that owns the protocol. |

A blank family + code with an **Open** condition clears the protocol.

### Access conditions

| Condition | Effect on the public |
| --- | --- |
| **Open** | Fully visible - no restriction. |
| **Attribution** / **Non-commercial** | Visible - these are *usage obligations*, not access restrictions. The obligation rides the export; the content stays viewable, and a provenance **badge** shows on the term page. |
| **Community voice**, **Seasonal**, **Gendered**, **Restricted**, **Sacred / secret** | **Restricted** - the term and any record tagged with it are hidden from guests and non-editors. |

Editors and administrators always bypass the gate (they see everything, restricted or not).

### Where the gate applies

A **restricted** protocol fails closed across every public surface at once:

- **Term page** (`/term/<slug>`) - 404 for guests.
- **Term browse** - the term is omitted from the list.
- **Public record display, print, and export** - records tagged with the term drop out.
- **OAI-PMH harvest** and the **RiC linked-data API** - excluded from `ListRecords` / `GetRecord` and the record list / single-record / export endpoints.
- **Portable offline export** - tagged records are withheld from the bundle (counted as `protocol` in the bundle's `disclosure-summary.json`).

A **usage-obligation** label (open / attribution / non-commercial) stays visible and simply renders a coloured badge on the term page.

> Term-level protocols here are the lightweight vocabulary-governance layer. For object-level ICIP infrastructure - communities, consent, notices, OCAP dashboards - see the **ICIP** user guide.

---

## Common gotchas

- **A restricted protocol hides the term everywhere at once.** If a term "disappears" for the public (404 on its page, gone from browse, dropped from OAI/RiC/exports) check its Community-protocol access condition - a restricted condition is doing exactly that by design. Editors still see it.
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
- **`ahgIcipPlugin`** — object-level Indigenous cultural protocols (communities, consent, notices), complementary to term-level community protocols.
- **Help articles**: *Multilingual Cataloguing*, *AHG Authority Records — User Guide*, *ICIP — User Guide*
