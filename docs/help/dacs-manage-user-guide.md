> Heratio Help Center article. Category: Cataloguing / Standards.

# DACS Description Editor

The DACS Description Editor lets archivists catalogue an archival record using the field set and area structure of DACS (Describing Archives: A Content Standard, 2nd edition). It presents one full description form, grouped into DACS-aligned areas, for a single information object identified by its slug. Saving the form writes the standard archival fields, DACS-specific properties (language and script notes, technical access), creator and access-point links, and publication status straight into the shared core description tables, and stamps the record's source standard as "DACS 2nd edition".

## Overview

Heratio is standard-neutral at its core: the same information object can be described with ISAD(G), RAD, DACS, or another rules set. This package provides the DACS view onto an existing archival description. It does not create new records and it does not maintain its own tables - it reads from and writes to the shared `information_object` / `information_object_i18n` tables and their related event, note, term, relation, property, and status tables, the same data any other description editor uses.

The editor is multilingual-aware. It loads and saves the translatable fields in the active interface locale (`information_object_i18n.culture`), so descriptions can be authored per language without overwriting another locale's text.

Every field on the form maps to a real DACS element. The areas follow the familiar archival multi-level description layout, rendered as a Bootstrap 5 accordion so each area can be expanded independently.

## Key features

- Single full-description form for one information object, opened by slug.
- DACS area grouping presented as collapsible accordion sections:
  - Identity area
  - Dates and creators
  - Content and structure
  - Conditions of access and use
  - Allied materials area
  - Notes area
  - Access points
  - Description control area
  - Administration area
- Writes the core archival fields on `information_object` and `information_object_i18n`.
- Sets `source_standard` to "DACS 2nd edition" on every save, and defaults the "Rules or conventions" field to the same value.
- DACS-specific properties stored on the `property` table: language and script notes (`languageNotes`) and technical access (`technicalAccess`).
- Language-of-material and script-of-material lists stored as serialized property values (`language`, `script`).
- Creator management via the `event` table (event type 111).
- Access points across four vocabularies and link types:
  - Subject access points (taxonomy 35)
  - Place access points (taxonomy 42)
  - Genre access points (taxonomy 78)
  - Name access points (actor relations, relation type 161)
- Related material descriptions (relation type 173), shown as links to the related records.
- Notes split by DACS note type: publication notes (type 220), archivist notes (type 174), and general notes (everything else).
- Publication status (Draft / Published) written to the `status` table (status type 158).
- Parent record shown as a link when the description sits below a collection root.
- Dropdown values for levels, repositories, description statuses, levels of detail, and display standards are loaded live from the database, never hardcoded.

## How to use

### Opening the DACS editor

The editor lives behind authentication at:

```
/admin/dacs-manage/edit/{slug}
```

where `{slug}` is the URL slug of the information object you want to describe. The route is named `ahgdacsmanage.edit` and accepts both GET (load the form) and POST (save). You must be logged in; the route group applies the `web` and `auth` middleware.

If no record matches the slug in the active locale, the editor returns a 404.

### Editing a description

1. Open `/admin/dacs-manage/edit/{slug}` for the record.
2. The page header reads "Edit archival description (DACS 2nd edition)". If the record has a parent, a "Parent:" link appears below the heading so you can navigate up the hierarchy.
3. Work through the accordion areas. The Identity area is expanded by default; the others are collapsed - click an area heading to open it.
4. Fill in the fields described below per area.
5. Click **Save** at the bottom. A green confirmation banner ("Description saved (DACS).") appears and the form reloads with the saved values. Use **Cancel** to return to the record's public page without saving.

Title is the only required field. If it is empty, validation fails and the errors are listed at the top of the form in a red banner.

### Identity area

- **Reference code** - the record identifier (`identifier`).
- **Title** (required) - the description title.
- **Alternate title**.
- **Level of description** - select; options come from taxonomy 34.
- **Extent and medium**.

### Dates and creators

- Existing dates (events) are listed read-only, showing the display date, start and end dates, and any associated actor name.
- Existing creators are listed. Creators are stored as events of type 111. The form submits creator actor IDs through `creatorIds[]`; a hidden `_creatorsIncluded` flag tells the save routine to rebuild the creator links from the submitted IDs.

### Content and structure

- Scope and content
- Archival history
- Immediate source of acquisition
- Appraisal, destruction and scheduling
- Accruals
- System of arrangement

### Conditions of access and use

- Conditions governing access
- Conditions governing reproduction
- Language of material (shown from the serialized `language` property)
- Script of material (shown from the serialized `script` property)
- Language and script notes (DACS property `languageNotes`)
- Physical characteristics and technical requirements
- Technical access (DACS property `technicalAccess`)
- Finding aids

### Allied materials area

- Existence and location of originals
- Existence and location of copies
- Related units of description
- Related descriptions - any records linked by relation type 173 are listed as links.

### Notes area

Notes are shown grouped by DACS note type and are read-only on this form:

- Publication notes (note type 220)
- Archivist notes (note type 174)
- General notes (all other note types)

### Access points

Existing access points are shown as badges and submitted back as hidden ID lists, so saving preserves the current links:

- Subject access points (taxonomy 35) - `subjectAccessPointIds[]`
- Place access points (taxonomy 42) - `placeAccessPointIds[]`
- Genre access points (taxonomy 78) - `genreAccessPointIds[]`
- Name access points (actor relations, type 161) - `nameAccessPointIds[]`

When the corresponding field is present in the submission, the editor clears the old links for that vocabulary and recreates them from the submitted IDs.

### Description control area

- Description identifier (`description_identifier`)
- Institution identifier (`institution_responsible_identifier`)
- Rules or conventions (`rules`) - defaults to "DACS 2nd edition"
- Status - select; options from taxonomy 44 (`description_status_id`)
- Level of detail - select; options from taxonomy 43 (`description_detail_id`)
- Language(s) of description
- Sources
- Revision history

### Administration area

- Repository - select; all repositories in the active locale (`repository_id`)
- Display standard - select; options from taxonomy 52 (`display_standard_id`)
- Publication status - Draft (159) or Published (160), written to the `status` table under status type 158.

### What a save writes

On POST the editor:

1. Validates that Title is present.
2. Updates the non-translatable fields on `information_object` (identifier, level, repository, description status, level of detail, description identifier) and sets `source_standard` to "DACS 2nd edition". Display standard is updated when submitted.
3. Updates the translatable fields on `information_object_i18n` for the active culture.
4. Saves the DACS properties `languageNotes` and `technicalAccess`, and the serialized `language` and `script` property lists.
5. Rebuilds creators (event type 111) when included.
6. Rebuilds subject, place, genre, and name access points when their fields are submitted.
7. Rebuilds related material descriptions (relation type 173) when submitted.
8. Upserts publication status (status type 158) when submitted.
9. Touches `object.updated_at`.

## Configuration

This package has no settings of its own. Its behaviour depends on shared platform configuration:

- **Interface locale** - the active locale determines which `*_i18n` rows are read and written. Switch language to author a different translation.
- **Controlled vocabularies** - level of description, description status, level of detail, display standard, and repository options are read live from the taxonomy/term tables (taxonomies 34, 44, 43, 52) and the repository list. Manage these values through the Dropdown Manager and the repository records, not in code.
- **Source standard label** - the package always stamps "DACS 2nd edition" as the source standard and as the default rules value. This is the package's defining behaviour and is fixed in the controller.
- **Authentication** - all access requires a logged-in user (`web` and `auth` middleware on the route group).

The package registers only its route and its view namespace (`dacs-manage`); it ships no database migrations because it operates entirely on the existing core description schema.

## References

- Source: packages/ahg-dacs-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/555
- Standard: DACS (Describing Archives: A Content Standard), 2nd edition
