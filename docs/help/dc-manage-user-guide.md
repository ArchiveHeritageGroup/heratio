> Heratio Help Center article. Category: Plugin Reference.

# Dublin Core Manage - User Guide

## Edit Archival Descriptions Using Simple Dublin Core

The Dublin Core Manage module gives editors a focused form for editing an
archival description with the Dublin Core Simple version 1.1 element set. It is
an alternative editing view for institutions that catalogue, or cross-walk, to
Dublin Core rather than the full ISAD(G) template.

The form maps each Dublin Core element to the matching field on the record, so
saving here updates the same underlying archival description.

---

## Overview

Dublin Core Manage presents one record at a time, organised into clear sections:
the core Dublin Core elements, a DC Type selector, read-only access points, the
material languages, and an administration block for repository, display standard,
and publication status.

When you save, the record is stamped with the source standard "Dublin Core Simple
version 1.1", and the relevant catalogue fields, access-point links, and
publication status are updated together.

---

## Key features

- Single edit form keyed to a record's slug.
- Edits the core Dublin Core elements:
  - **dc:identifier** - the reference identifier.
  - **dc:title** - the title (required).
  - **dc:description** - scope and content.
  - **dc:format** - extent and medium.
  - **dc:rights** - access conditions.
  - **dc:source** - location of originals.
- **dc:type** multi-select drawn from the DC Type taxonomy.
- Read-only display of **dc:subject** (subject access points), **dc:coverage**
  (place access points), and **dc:creator** (from the record's events).
- Read-only display of material languages (**dc:language**).
- Administration block: repository, display standard, and publication status
  (Draft or Published).

---

## How to use

1. Log in with an account that has edit rights (the route requires `auth` plus the `acl:update` permission — it is not restricted to administrators).
2. Open the Dublin Core editor for a record at
   **`/admin/dc-manage/edit/{slug}`**, where `{slug}` is the record's slug.
3. Work through the sections:
   - **Dublin Core elements** - fill in identifier, title (required),
     description, format, rights, and source.
   - **dc:type** - select one or more types from the list.
   - **Access points** - review the subjects, places, and creators shown here.
     These are displayed for context.
   - **dc:language** - review the material languages.
   - **Administration** - set the repository, the display standard, and the
     publication status (Draft or Published).
4. Save the form. The record's source standard is recorded as "Dublin Core Simple
   version 1.1", the catalogue fields and access-point links are updated, the
   publication status is written, and the record's modified timestamp is
   refreshed.
5. Use **Cancel** to return to the record without saving.

---

## Configuration

There are no module settings to set. The form draws its dropdown options from
the catalogue's own controlled vocabularies:

- **DC Type** options come from the DC Type taxonomy.
- **Repository** options come from the repository records.
- **Display standard** options come from the display standards taxonomy.
- **Publication status** is Draft or Published.

The editing culture follows the application's current language, so the fields you
edit are stored against that culture.

---

## References

- Source package: `packages/ahg-dc-manage/`
- GitHub issue: [GH #558](https://github.com/ArchiveHeritageGroup/heratio/issues/558)
