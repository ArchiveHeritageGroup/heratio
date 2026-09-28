# Storage locations, CAAIS and the AtoM wish lists - September 2026

Heratio v1.155.0 and v1.155.1 (28 Sep 2026) put hierarchical storage locations live on heratio.org, on the same tables as the AtoM plugin (atom-ahg-plugins v3.110.13). The work started from a review of two AtoM community wish lists, which showed the same requests coming back six years apart. This note records what was reviewed, what was decided, what shipped, and the operational lessons.

Tracking issues: heratio#1514 and its parity twin atom-ahg-plugins#193.

## The two wish lists

**AtoM Foundation AGM 2026** (September 2026) - a sticky-note board of user wishes. **AtoM 3 wish list, 2020** - a spreadsheet collected around TAATU 2020 (May-June 2020): 63 requests, 5 user stories and a milestone sheet.

Most of the 2026 board was already on the 2020 list: accessibility (WCAG AA), a CAAIS-based accessions module, single sign-on, better physical storage, custom metadata fields, API examples and search. AtoM 3 never shipped, so the wishes carried over. One requester filed the WCAG request in 2020 and is on the 2026 board as well. One 2026 note asked for a "sandbox/testing environment for new developments (like Heratio)", naming Heratio directly.

Heratio already answers a good share of both lists: closure tables instead of nested sets (heratio#1333), Laravel 12 on PHP 8.3, REST v1/v2 plus GraphQL, the audit trail, reading-room bookings and access requests, share links, IIIF with Cantaloupe, dedupe and merge, report builder, ODRL rights enforcement, multi-tenancy and the Docker stack.

Gaps found (carried on heratio#1514): nested physical locations, CAAIS, a repository link on accessions, working LDAP/SAML login (only an LDAP settings page exists, with no bind code), search and replace, batch edit, one-step numeric sort of tree children, remote logging config, session-length warning, and a formal WCAG audit.

## CAAIS in brief

The Canadian Archival Accession Information Standard, Final 1.0, is dated 15 May 2019 and was produced by the Canadian Council of Archives' National Archival Accession Standard Working Group; the CCA put the PDF on its site in December 2022. It is a content standard for what to record when custody passes to an archive, in seven sections: Identity, Source, Materials, Management, Events, General, Control. The mandatory elements are an identifier, the creator, the date of materials, an extent statement, the date physical custody transferred (and legal control, if different), and the record's creation date.

Heratio's accession tables cover most of it, often more richly (rights, appraisal, events, timeline). The gaps are structured extent, source confidentiality, language of material, rules or conventions used, the revision agent, a repository link, structured preservation requirements, the mandatory custody and control events, and a CAAIS export. CAAIS belongs as a pluggable Canadian profile over the core accession model, the same way GRAP 103 does for South Africa.

The Ontario tender evaluation (`ontario/AtoM_Heratio_Tender_Evaluation_RFB_21279.md` in the archive tree) overclaimed: "Full CAAIS support built-in", "AtoM is the reference CAAIS implementation", and CAAIS areas that do not exist in the standard. It was corrected on 28 Sep 2026: five standards native plus most of CAAIS with named gaps, FR_1.1 rescored from 6/6 to 4/6, DS-1 from Full to Partial, and the original kept as a dated .bak beside it. The functional total stays 130/156, because the old rows actually summed to 132.

## Storage locations - what shipped

Before this work there were five flat location models and no tree: free-text `physical_object_i18n.location`, the building/floor/room columns on `physical_object_extended`, one-level strongrooms, the shelf/box columns on `information_object_physical_location`, and per-object `spectrum_location` rows. Renaming a room meant editing every row, nothing could be counted up the tree, and containers could not nest.

**The design decision (Johan, option 2 on atom-ahg-plugins#193):** `parent_id` is the source of truth, and `ahg_storage_location_closure` (ancestor, descendant, depth) is a maintained index, kept in step inside the same transaction as every create, move and delete. That matches Heratio's hierarchy convention and makes subtree, path and cycle checks single queries.

- **AtoM, atom-ahg-plugins v3.110.13:** closure table, idempotent install backfill, `rebuildClosure()`, and `testing/storage-location-closure-check.php`.
- **Heratio v1.155.0:** a port of the AtoM module in `ahg-storage-manage`. It covers browse with tree and search; a page per location; create and edit with a parent picker that excludes the location's own subtree; delete refused while children exist; JSON endpoints. Types come from the Dropdown Manager (`storage_location_type`), units from `capacity_unit`. Reads need a login, writes ACL, delete admin. The shared `ClosureMaintenanceService` maintains the closure; `php artisan ahg:build-closure --table=ahg_storage_location` rebuilds it. The tables self-install on first boot.
- **Heratio v1.155.1:** Storage locations link in the admin menu, under Strongrooms.
- **AtoM, atom-ahg-plugins v3.111.0** (by the archive session): each object's current location (`ahg_physical_object_location`) and an append-only movement log (`ahg_storage_movement`) covering object and location moves. Bulk moves are one row per object with a shared `batch_id`; name and username snapshots keep history readable; RESTRICT foreign keys stop a delete from erasing history. The Heratio port is with the heratio-dev session.

Still open: the Heratio movement log, containers inside containers, a bulk relocation screen, capacity roll-up, and migrating the flat location fields into the tree.

## Operational lessons

- **`bin/unlock` needs a trailing slash for a directory lock.** `./bin/unlock packages/ahg-storage-manage` reports success and unlocks nothing; the manifest entry is `packages/ahg-storage-manage/`.
- **New directories on heratio-dev must be writable by www-data.** A folder created as another user blocked a www-data rebase, because git could not unlink the files inside it. After creating one, grant www-data rwx with a default ACL.
- **heratio-dev had fallen one commit behind origin** (a docs release made on prod), so the v1.155.0 push was rejected and needed a rebase as www-data before the tag could be pushed.
- **The release workflow creates a bare GitHub release** ("Automated install artifacts") when none exists for a tag. Creating the release with real notes first means the workflow only attaches the schema and seed files.
- **Prod pulls run as www-data.** A pull as any other user leaves files that later pulls cannot overwrite.
- **Heratio development belongs to the heratio-dev session.** The prod-repo session hands code work over rather than building on dev itself.
