> Heratio Help Center article. Category: Cataloguing & Description.

# RAD Description Editor

The RAD editor lets you create and edit an archival description using the Rules for Archival Description, a multi-level descriptive standard. It presents a RAD-shaped form over the same information object that the rest of Heratio manages, so a record described in RAD remains fully searchable and linkable across the platform.

---

## Overview

RAD (Rules for Archival Description) is one of several descriptive standards Heratio supports as a **pluggable cataloguing view** over a single underlying record. The same information object can be described and displayed under different standards (for example ISAD(G), DACS, MODS, or RAD); choosing RAD does not change the record's identity, hierarchy, or relationships, only the set of elements you edit and how they are grouped.

The RAD editor is delivered by the `ahg-rad-manage` package. It writes to the standard Heratio description tables (the `information_object` record, its translatable `information_object_i18n` fields, events, access points, notes, and so on) plus a small set of RAD-specific elements stored as named properties. Because the storage is shared, switching a record's display standard to RAD does not migrate or duplicate data - it simply surfaces the RAD-specific fields alongside the common ones.

When a description is saved through this editor, its `source_standard` is stamped as **"RAD version Jul2008"**, identifying RAD as the rules followed.

---

## Key features

- **RAD-shaped editing form** - the description areas are grouped into the standard RAD areas rather than the ISAD(G) layout.
- **RAD-specific elements** - fields that exist in RAD but not in plain ISAD(G), such as statements of responsibility, edition statement of responsibility, scale and projection statements, publisher's series elements, and standard number, are stored as named properties on the record.
- **Multilingual content** - all translatable description fields are edited in the active interface language, so the same record can carry RAD descriptions in multiple languages.
- **Full access-point management** - subject, place, genre, and name access points, plus RAD material type, are editable from the same form.
- **Events** - creation and other dated events (with type, actor, and date) are edited inline.
- **Languages and scripts** - of both the material and the description, stored as multi-value properties.
- **Alternative identifiers** - any number of additional identifiers can be recorded.
- **Publication status** - publish or unpublish the record directly from the editor.

---

## How to use

### Open a record in the RAD editor

The editor is reached at:

```
/admin/rad-manage/edit/{slug}
```

where `{slug}` is the record's slug (the same slug used on its public show page). The route requires an authenticated user. In practice you reach it from the record's show page when that record is displayed under the RAD standard, via the standard-switch control that links to the RAD edit form.

The page renders an accordion-style form titled "Edit archival description", with each RAD area as a collapsible section.

### Form areas

The form groups elements into the RAD areas below. The first section is expanded by default; the rest are collapsed and expand on click.

| Section | Contents |
|---|---|
| **Title and statement of responsibility** | Reference identifier, title (required), title statement of responsibility, other title information, alternate title, level of description, material type |
| **Edition** | Edition, edition statement of responsibility |
| **Class of material specific details** | Cartographic scale, projection, coordinates, architectural scale, issuing jurisdiction and denomination |
| **Dates of creation** | Events (date display, start/end dates, type, actor) |
| **Physical description** | Extent and medium |
| **Publisher's series** | Title proper, parallel title, other title information, statement of responsibility, numbering, and note - all relating to the publisher's series |
| **Archival description** | Scope and content, custodial (archival) history, immediate source of acquisition, arrangement, accruals, appraisal |
| **Standard number** | Standard number |
| **Notes** | Access conditions, reproduction conditions, physical characteristics, finding aids, language notes, plus publication, archivist's, and general notes |
| **Access points** | Subject, place, genre, and name access points |
| **Allied materials area** | Location of originals, location of copies, related units of description, related material descriptions |
| **Description control area** | Description identifier, institution-responsible identifier, rules (defaults to "RAD Jul 2008"), description status, level of detail, sources, revision history |
| **Administration** | Holding repository, display standard, publication status |

### Save your changes

Submit the form to save. The title is required; saving fails validation if it is blank. On a successful save the editor stamps the record's `source_standard` as "RAD version Jul2008", updates the record's modified timestamp, and reloads with a confirmation message. Each field group is only rewritten when its corresponding form data is present, so the editor will not clear data it did not render.

---

## How RAD relates to information objects

Every RAD description **is** an ordinary Heratio information object. The editor reads and writes:

- **Core record fields** in `information_object`: identifier, level of description, collection type, repository, parent, description status, description detail (level of detail), description identifier, display standard, and the `source_standard` stamp.
- **Translatable fields** in `information_object_i18n` for the active language: title, alternate title, edition, extent and medium, archival history, acquisition, scope and content, appraisal, accruals, arrangement, access conditions, reproduction conditions, physical characteristics, finding aids, location of originals, location of copies, related units of description, institution-responsible identifier, rules, sources, and revision history.
- **RAD-specific elements** as named rows in the `property` table (with translatable values), covering the title/edition/series statements of responsibility, scale and projection statements, issuing jurisdiction and denomination, standard number, publisher's series elements, and language notes.
- **Events** (dates) with their type, actor, and free-text date display.
- **Access points** via term relations and name relations: subjects, places, genres, RAD material type, and name access points.
- **Languages and scripts** of both the material and the description, stored as serialized multi-value properties.
- **Alternative identifiers** as repeatable properties.
- **Publication status** in the `status` table.

Because the data lives in the shared description model, a record edited here behaves exactly like any other description in browse, search, the public show page, OAI-PMH harvesting, and metadata export. Switching its display standard to ISAD(G) or another standard later does not lose the data; standard-specific elements simply stop being shown by that standard's view.

---

## Configuration

The RAD editor has no settings of its own. Its dropdown options are sourced from Heratio's controlled vocabularies (the term taxonomies), consistent with the platform rule that enumerated values come from managed vocabularies rather than hardcoded lists. The selects on the form are populated from:

| Form control | Vocabulary |
|---|---|
| Level of description | Levels of description taxonomy |
| Material type | RAD material type taxonomy |
| Repository | Holding repositories (actors) |
| Description status | Description status taxonomy |
| Level of detail | Description detail taxonomy |
| Display standard | Display standard taxonomy |
| Event type | Event type taxonomy |

Repositories are listed from the actors that are repositories. Access points (subject, place, genre, name) are selected from their respective vocabularies and actor records.

To make RAD the standard shown for a record, set its display standard accordingly in the Administration section. RAD is jurisdiction-neutral in Heratio: it is offered as a descriptive standard option for any institution that uses it, alongside the other supported standards, and is never the forced default.

---

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| 404 when opening the editor | The slug does not match a record, or there is no description in the active interface language |
| Save rejected | The title field is empty; RAD requires a title |
| A field appears blank after save | That field group was not rendered on the form you submitted; only present groups are rewritten |
| RAD-specific fields not visible on the public page | Confirm the record's display standard is set to RAD in the Administration section |
| Dropdown is empty | The backing vocabulary has no terms; add terms via the Dropdown Manager / taxonomy before editing |

---

## References

- **Source package:** `packages/ahg-rad-manage/`
- **Controller:** `RadManageController`
- **Route:** `GET|POST /admin/rad-manage/edit/{slug}` (named `ahgradmanage.edit`, requires authentication)
- **View:** `rad-manage::edit`
- **Source standard recorded:** "RAD version Jul2008" (migrated from the AtoM `sfRadPlugin`)
- **Related RAD display template:** `show-rad.blade.php` in `ahg-information-object-manage`
- **GitHub issue:** [#613](https://github.com/ArchiveHeritageGroup/heratio/issues/613)
