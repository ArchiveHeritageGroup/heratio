# ahgInformationObjectManagePlugin - Archival Descriptions (ISAD(G))

The **ahgInformationObjectManagePlugin** is Heratio's core cataloguing surface - the place where archivists create, edit, and manage **archival descriptions** per ISAD(G) (General International Standard Archival Description).

Information objects (IOs) are the records visitors discover and researchers cite. Every record in the GLAM browse, every URL ending in a slug like `/cabinet-minutes-1965`, every digital object's parent - they're all information_object rows.

---

## What an information object is

An **information_object** describes one archival entity at one **level of description** in the hierarchy. ISAD(G) defines six standard levels:

```
Fonds  →  Sub-fonds  →  Series  →  Sub-series  →  File  →  Item
```

Each row in the `information_object` table sits at one level and points at its parent via `parent_id`, forming a tree. The MPTT `lft`/`rgt` columns let queries fetch a fonds and *all* descendants in one range scan.

A single PDF, photograph, audio file, or other digital file lives in a `digital_object` row that points back at its information_object via `object_id`. A photograph isn't an IO - the *description* of the photograph is the IO; the file is the digital object attached to it.

---

## Where it lives

| Surface | URL |
| --- | --- |
| Browse (faceted) | `/glam/browse` (preferred) or legacy `/informationobject/browse` |
| Show / public page | `/<slug>` (catch-all) |
| Add | `/informationobject/add` |
| Edit | `/<slug>/edit` |
| Move (reparent) | `/<slug>/move` |
| Rename slug | `/<slug>/rename` |
| Duplicate | `/informationobject/create?parent_id=<id>&copy_from=<id>` |
| Delete | `/<slug>/delete` |
| Print | `/<slug>/print` |
| Inventory (children list) | `/<slug>/inventory` |
| Reports | `/<slug>/reports` |
| Tree view | embedded panel on every show page |

---

## ISAD(G) field set

The edit form is structured into the seven ISAD(G) areas:

| ISAD area | Heratio fields (a sample) |
| --- | --- |
| **3.1 Identity** | identifier, title, creation/accumulation dates, level of description, extent and medium |
| **3.2 Context** | name of creator (linked actor), administrative/biographical history, archival history, immediate source of acquisition |
| **3.3 Content & structure** | scope and content, appraisal/destruction, accruals, system of arrangement |
| **3.4 Conditions of access & use** | access conditions, conditions of reproduction, language/script, physical characteristics, finding aids |
| **3.5 Allied materials** | originals, copies, related units of description, publication note |
| **3.6 Notes** | general note + dedicated archivist's note |
| **3.7 Description control** | description identifier, institution responsible, rules/conventions, status, level of detail, dates of description |

All title-style fields (title, scope_and_content, archival_history, …) live in `information_object_i18n` per culture; structural fields (identifier, parent_id, level_of_description_id, repository_id) live in `information_object`.

The plugin also supplements ISAD(G) with optional sidebar panels (visible when the relevant secondary plugin is enabled): Spectrum data, Heritage Assets, Provenance, Condition reports, Digital preservation, AI tools, Privacy & PII, RiC actions.

---

## Common workflows

### Create a new fonds

1. `/informationobject/add` (or click **Add** in the navbar).
2. Set **Level of description = Fonds**, **Parent = root**.
3. Fill identifier, title, creation dates.
4. Save. The IO is created in `draft` status by default - flip to `published` from `/<slug>/update-status`.

### Add a series under that fonds

1. From the fonds show page, click **Add child**.
2. Level = Series; Parent is auto-populated.
3. Title, dates, scope and content. Save.

### Bulk import via CSV

1. `/ingest` (preferred) or `/informationobject/import/csv` (classic AtoM-style).
2. Upload your CSV (see the AtoM Reference template - `legacyId`, `parentId`, `title`, `levelOfDescription`, `repository`, `dates`, `scopeAndContent`, etc.).
3. **Map** columns to fields, **preview** the first 50 rows, **commit**. Background job runs the import; progress at `/admin/jobs`.

### Attach a digital object

Method A - at IO creation: include `digitalObjectURI` in the CSV manifest.
Method B - after the fact: open the IO show page and use **Edit digital object** in the right sidebar (when `ahgInformationObjectManagePlugin` and `ahgDigitalObject` features are wired). Or just upload via `/ftpUpload/index` and link via CSV.

### Move/reparent

Use `/<slug>/move`. The MPTT lft/rgt of the entire subtree is recalculated server-side. Cheap for small subtrees, costly (~seconds) for fonds with thousands of descendants - runs as a background job for very large moves.

---

## Sidebar plugin extensions

The right sidebar of an IO show page is composed of opt-in panels, each gated by a plugin enable flag:

| Sidebar panel | Plugin | Surface |
| --- | --- | --- |
| Provenance | `ahgProvenancePlugin` | chain-of-custody log |
| Condition reports | `ahgConditionPlugin` | Spectrum 5.0 condition assessment |
| Spectrum data | `ahgSpectrumPlugin` | museum procedures |
| Heritage Assets | `ahgHeritageAccountingPlugin` | GRAP/IPSAS valuation |
| Cite this Record | `ahgResearchPlugin` | citation builder |
| Digital Preservation (OAIS) | `ahgPreservationPlugin` | PREMIS events, fixity |
| AI Tools | `ahgAIPlugin` | NER, summary, translate, animate |
| Privacy & PII | `ahgPrivacyPlugin` | redaction, dashboard |
| RiC Actions | `ahgRicExplorerPlugin` | RiC graph, JSON-LD export |

Each panel autoframes itself off when its plugin is disabled - you don't see "AI Tools" if `ahgAIPlugin` is off.

---

## Permissions

| Action | Required role |
| --- | --- |
| Browse / view (published) | Anonymous |
| Browse / view (drafts) | Editor / Admin |
| Add, edit, move, rename | Editor (`acl:create`, `acl:update`) |
| Delete | Admin (`acl:delete`) |
| Update status (publish) | Editor with `acl:publish` |
| Reproduction request | Researcher (issues an ODRL `odrl:reproduce` check) |

Per-record overrides are configured at `/admin/security-clearance` - useful when one record in an otherwise-public fonds needs to be embargoed.

---

## Permalinks, slugs, and identifiers

Three different stable IDs:

- **`information_object.id`** - the database PK; never visible to users; never reused.
- **`information_object.identifier`** - the *archival* identifier (e.g. "AHG-001-1965"). Cataloguer-set. Unique within a fonds. **This is what archivists cite.**
- **`slug.slug`** - the URL-friendly form (e.g. `cabinet-minutes-1965`). Auto-generated from the title; can be renamed manually.

The slug is the public URL component. Renaming a slug breaks old bookmarks unless you create an HTTP redirect (admin task; not currently UI-driven).

---

## Common gotchas

- **`parent_id = 1`** is the IO root sentinel. Top-level fonds always have `parent_id = 1`. Don't delete row id 1.
- **Publication status** lives in the `status` table (`type_id=158`), **not** in `information_object`. To flip a record from draft to published, use `/<slug>/update-status` - don't UPDATE the column directly.
- **Slug catch-all** - the route `/{slug}` is the last route in the table. Any new top-level URL must be added to the slug-route exclusion list (`packages/ahg-information-object-manage/routes/web.php`) or it'll be intercepted as a slug-resolve.
- **MPTT integrity** - manual SQL DELETE/UPDATE on `information_object` *will* corrupt the lft/rgt range. Always go through the controller (or `php artisan ahg:nested-set-rebuild`) when you must touch hierarchy.
- **Soft moves on large subtrees** - moving a fonds with 100k+ descendants takes a long time. Plan it during a low-traffic window or use `php artisan ahg:io-move <fromId> <toId>` from the CLI for non-blocking execution.

---

## Related

- **`ahgDisplayPlugin`** - the GLAM browse + faceted search front-end
- **`ahgRepositoryManagePlugin`** - the institutions that hold these IOs
- **`ahgActorManagePlugin`** - the people/orgs that created them
- **`ahgIngestPlugin`** - the CSV / file batch import wizard
- **`ahgFormsPlugin`** - admin-configurable IO edit forms (per repository)
- **Help articles**: *Spectrum 5.0*, *Provenance Management*, *Condition Reports*
