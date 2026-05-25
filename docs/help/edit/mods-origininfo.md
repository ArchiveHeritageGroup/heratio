# MODS originInfo and notes (edit form)

> Heratio Help Center article. Category: Metadata / Edit. Slug: `mods-origininfo`.

## Overview

The MODS edit form (`/admin/mods-manage/edit/{slug}`) now exposes an **originInfo** accordion and a **note** accordion alongside the existing core MODS elements. originInfo is the MODS 3.5 element that captures *who issued the resource, when, and where*. The new inputs map directly onto the MODS XML you get when you click **MODS 3.5 XML** from the show page's Export dropdown.

## originInfo inputs

| Form field | MODS element on export | Backing storage |
|---|---|---|
| dateCreated (display) | `<originInfo><dateCreated>` | `event.type_id = 111` (creation) + `event_i18n.date` |
| dateCreated (ISO 8601) | `<originInfo><dateCreated encoding="iso8601">` | `event.start_date` on the same row |
| dateIssued (display) | `<originInfo><dateIssued>` | `event.type_id = 114` (publication) + `event_i18n.date` |
| dateIssued (ISO 8601) | `<originInfo><dateIssued encoding="iso8601">` | `event.start_date` on the same row |
| publisher (actor) | `<originInfo><publisher>` | `event.actor_id` on the publication event row |
| publisher (free text) | `<originInfo><publisher>` (only when no actor is selected) | `property.name = 'mods:publisher'` (serialized) |
| placeOfPublication | `<originInfo><placeOfPublication><placeTerm type="text">` | `relation` row of type 162 between the IO and a taxonomy-42 term |

The form pre-fills from the first creation / publication event already on the record. On save:

1. The two managed event types (111 and 114) are deleted and re-inserted from the form. Other event types on the record (deposit, accumulation, custody transfer, etc.) are left untouched.
2. The `publisher (actor)` autocomplete takes precedence over `publisher (free text)`. If an actor is chosen, the free-text field is cleared on the back end.
3. The placeOfPublication autocomplete is filtered against taxonomy 42 (Places) so suggestions only include place terms.

## Subject / name access points are now editable

The Subject / name access points accordion previously displayed read-only badges. It now uses the autocomplete-driven multi-select component for all three lists:

- **subject (topic)** — taxonomy 35
- **subject (geographic)** — taxonomy 42
- **name** — actor autocomplete

Removing a chip removes the link; selecting a suggestion adds a chip. Save the form to persist.

## mods:note

The new **note** accordion exposes a single textarea that maps to `<note type="general">` on export. Backing storage is `property.name = 'mods:note'` (serialized) so the value sits alongside the other AtoM-style structured properties.

## Where the value appears on download

Open the show page, click **Export → MODS 3.5 XML**. The downloaded file will contain (when populated):

```xml
<originInfo>
  <publisher>...</publisher>
  <dateIssued encoding="iso8601">1901-04-01</dateIssued>
  <dateIssued>spring 1901</dateIssued>
  <dateCreated encoding="iso8601">1900-01-01</dateCreated>
  <dateCreated>circa 1900</dateCreated>
  <placeOfPublication><placeTerm type="text">...</placeTerm></placeOfPublication>
</originInfo>
...
<note type="general">...</note>
```

The same refinements are emitted by the `ModsSerializer` used for OAI-PMH dissemination, so harvesters pick up the new structure without any client-side change.

Issue: [#662 MODS Phase 2 (originInfo, editable access points, note)](https://github.com/ArchiveHeritageGroup/heratio/issues/662).
