# Editing Descriptions in MODS

> Heratio Help Center article. Category: Cataloguing.

Edit an archival description using the MODS (Metadata Object Description Schema) view, a form organised around MODS elements: title, identifier, resource type, subjects, places, names, languages, origin information (creation and publication), publisher, place of publication, access conditions, and notes.

---

## Overview

MODS is a bibliographic metadata standard maintained by the Library of Congress. The MODS Manage tool gives you an alternative editing form for an existing archival description, laid out around MODS elements rather than the standard ISAD(G) edit form. It reads and writes the same underlying record, so a change made here is the same record everywhere else in the catalogue. Saving through this form stamps the record's source standard as "MODS version 3.3".

Use it when you are cataloguing material that is most naturally described in MODS terms, or when you want a focused form for the MODS-aligned fields.

---

## Key features

- **Core descriptive fields.** Edit the title (required), identifier, scope and content, and access conditions.
- **MODS resource type.** Assign one or more resource types from the MODS resource type vocabulary.
- **Access points.** Manage subject access points, place access points, and name access points (linking the record to authority records as named contributors).
- **Languages of material.** Record one or more languages the material is in.
- **Origin information (originInfo).** Record creation and publication events with both a free-text display date and an optional structured (ISO 8601) date.
- **Publisher.** Attach a publisher either as a linked authority record or as free text when no authority record applies.
- **Place of publication.** Link the record to a place term.
- **General note.** Add a MODS note.
- **Repository and display standard.** Set the holding repository and the display standard.
- **Publication status.** Set the record's publication status (for example draft or published).
- **Parent context.** The form shows the parent record (with a link) when the description sits inside a hierarchy.

---

## How to use

1. Open the MODS edit form for a record at `/admin/mods-manage/edit/{slug}` (where `{slug}` is the record's address). You must be signed in.
2. Fill in or revise the fields:
   - **Title** is required.
   - Set the **identifier** and **repository** as needed.
   - Choose one or more **MODS resource type** values.
   - Add **subject**, **place**, and **name** access points. Name access points link to existing authority records.
   - Record the **languages of material**.
   - Under origin information, enter a **creation date** and a **publication date**. You can supply a human-readable date and, optionally, a structured ISO date alongside it.
   - Set the **publisher**: pick an authority record, or type a free-text publisher name when there is no authority record. (A linked authority record takes precedence over free text.)
   - Set the **place of publication**.
   - Add a **note** and edit **scope and content** and **access conditions** as needed.
   - Set the **publication status**.
3. Click save. The record is updated, its source standard is set to "MODS version 3.3", and you are returned to the form with a confirmation message.

---

## Configuration

- **No enumerated values are hardcoded.** Resource types, subjects, places, display standards, and repositories are all drawn from the catalogue's controlled vocabularies and authority records, so the available options reflect your own taxonomy.
- **Creation and publication events are managed by this form.** When you save, the tool replaces the record's existing creation and publication events with the values from the form. Other event types on the record are left untouched.
- **Publisher and place of publication** behave as single values in this form: one publisher and one place of publication per record.
- Access requires you to be signed in. The form lives under the `/admin` path.

---

## References

- Source: packages/ahg-mods-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/600
