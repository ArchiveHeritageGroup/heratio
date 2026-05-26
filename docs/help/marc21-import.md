# MARC21 / MARCXML Import

Heratio can import MARC21 bibliographic records expressed in MARCXML
(the Library of Congress XML serialization of MARC21). Use this when a
library partner ships its catalogue as MARCXML and you need to load those
records into Heratio's archival catalogue as Information Objects.

## When to use this

- A partner library has sent you a MARCXML export of one or more bib records.
- You ran an external MARCXML editor and want to push edits back into Heratio.
- You have run a Heratio MARCXML export and need to re-import the same file
  on another instance.

## What you need

- A MARCXML file (`.xml`) up to 50 MB. The file must validate against the
  LoC MARC21 slim schema (`http://www.loc.gov/MARC21/slim`).
- An admin login on Heratio.

## Steps

1. From the main menu, open **Metadata Export** (or browse to
   `/admin/metadata-export/index`).
2. Click the **MARCXML Import** button at the top.
3. Choose your `.xml` file.
4. Pick the language / culture code for the import (default: `en`).
5. Click **Validate & Preview**.

Heratio will:

- Validate the file against the LoC schema (vendored offline).
- Parse every `<record>` element.
- For each record, look up an existing Heratio Information Object by its
  MARC 001 control number (matching `io.identifier`, or `io.id` as a
  fallback).
- Show a table with one row per record:
  - **CREATE** badge if no existing IO matches the 001 - a new IO will be
    inserted.
  - **UPDATE** badge (with the matched IO id) if an existing IO will be
    overwritten.

Review the preview. If anything looks wrong, click **Cancel** and start
over with a corrected file.

When you are happy, click **Commit Import**. Heratio will:

- Insert or update the IO + i18n rows.
- Emit a tamper-evident audit row per record (action name
  `marcxml_create` or `marcxml_update`).

The results screen shows the new IO id, the action taken, and the audit row
id for every record processed.

## Fields imported

| MARC field | Heratio column                                    |
|-----------:|---------------------------------------------------|
| 001        | `information_object.identifier`                   |
| 245$a      | `information_object_i18n.title`                   |
| 300$a      | `information_object_i18n.extent_and_medium`       |
| 506$a      | `information_object_i18n.access_conditions`       |
| 520$a      | `information_object_i18n.scope_and_content`       |
| 540$a      | `information_object_i18n.reproduction_conditions` |
| 541$a      | `information_object_i18n.acquisition`             |
| 544$a      | `information_object_i18n.related_units_of_description` |
| 561$a      | `information_object_i18n.archival_history`        |

Subjects (650), places (651), genres (655) and creators (100/110/111) are
parsed and captured in the audit metadata payload. Direct insertion of new
terms/actors is not in scope for Phase 2 - operators should reconcile those
via the existing access-point tooling.

## Round-trip

Records exported via Heratio's MARCXML serializer round-trip cleanly through
this importer: title, identifier, scope, extent, dates, access/reproduction
conditions, archival history and acquisition are all preserved.

## Troubleshooting

- **"Schema validation issues" warning**: the upload is not valid MARC21
  slim. Open the file in an XML editor and fix the highlighted lines.
- **"No 245$a title" warning on a row**: that record is skipped on commit
  because MARC21 requires a 245.
- **All rows show CREATE but you expected UPDATE**: the MARC 001 value does
  not match any existing IO. Either the source 001 was never set by the
  partner system, or it differs from Heratio's `io.identifier`. Edit the
  MARCXML so 001 matches the target IO's identifier and re-upload.
- **Audit column shows `-`**: the chained audit writer was unavailable
  (signing key not installed). The IO write still succeeded; the audit
  fallback is unsigned.
