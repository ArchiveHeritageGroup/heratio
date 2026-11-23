> Heratio Help Center article. Category: Library.

# MARC Source Records and Version History

Heratio keeps the MARC record a catalogue entry came from, alongside the working catalogue data. When a record is imported, what arrived is stored as a version. Later edits add further versions, so you can see what a supplier sent, what your cataloguers changed, and when.

This gives you three practical abilities. You can download a stored record, compare any version with another or with the current catalogue, and re-run the field mapping over an imported record to pick up values that were never filled in.

## Who can use it

The source record pages need the update permission. They open from the MARC Editor: go to **Library Management > MARC Editor**, enter the item ID under **Edit Existing Records**, and choose **Source record** at the top of the editor. The address has the form `/library-manage/marc/{id}/source`, where `{id}` is the library item number.

## What gets stored

Each version carries an event, a format, a size and a date. The event says why the version exists.

| Event | When it is written |
| --- | --- |
| Imported | A MARCXML file, a binary MARC file or a copy cataloguing import created or updated the item |
| Baseline before first edit | The catalogue as it stood just before the first change, or before a matched record was re-imported |
| Edited | A cataloguer saved a change in the MARC Editor or the cataloguing form |
| Mapping replayed | Someone applied values from the Replay field mapping page |

: Version events on the source record page

The **Exact source** column tells you whether the stored version is byte for byte what arrived. A single-record file or a binary record is exact. One record taken from a batch file is not, because it is re-serialised as UTF-8 and the original batch file is not kept. Edited, baseline and replay versions are generated from the catalogue and are never treated as a source.

A save that changes nothing except the MARC 005 timestamp does not create a new version.

## View and download a version

1. Open the record in the MARC Editor and choose **Source record**.
2. Read the table of stored versions, newest first.
3. In the Export column, choose **MARCXML** for the XML form, or **Original bytes** where it is offered.

Original bytes come down as a `.mrc` file for a binary record and as `.xml` for an XML file held in another character encoding. A MARC-8 binary record may have no MARCXML form at all, in which case only the original bytes are available.

## Compare versions

**Compare source with current record** sets the most recent imported version against what the catalogue holds now. If the item was never imported, the oldest stored version is used instead. To start from a particular version, choose **Compare** on its row.

The result lists the fields found only in the earlier version, the fields found only in the later one, and a count of fields that did not change. If the page says the versions cannot be compared, either nothing is stored or the source is a binary record with no MARCXML form.

## Replay the field mapping

Replay reads the most recent imported version through the current mapping and shows what it would put into the catalogue. Nothing changes until you apply it, and an edited version is never used as the source.

1. On the source record page, choose **Replay field mapping**.
2. Review each row. The table shows the field, the catalogue value, the value from the source and a status.
3. Tick the fields you want. Empty fields arrive ticked. Fields that differ arrive unticked, so a cataloguer's correction survives unless you choose to overwrite it.
4. Choose **Apply selected**.

The statuses are **Same**, **Empty, can be filled**, **Differs from catalogue** and **Cannot be stored**. The last means the value is too long for the field or could not be decoded safely, and it cannot be selected.

Replay covers subtitle, ISBN, ISSN, pagination, publisher, place and date of publication, call number, classification scheme, material type, and the MARC leader, 005 and 008 values. Applying writes to the audit log and adds a Mapping replayed version.

## Replay from the command line

Administrators can run the same comparison across every imported item:

```bash
php artisan ahg:library-marc-replay
php artisan ahg:library-marc-replay --item=1234
php artisan ahg:library-marc-replay --apply
```

Without `--apply` the command only reports how many fields could be filled, how many differ and how many are unusable. With `--apply` it writes values into empty fields only. It never overwrites a value that differs, which remains a decision for the screen.

## Troubleshooting

If the page says no source record is stored, the item was catalogued before source records were kept and has not been edited since. Its first edit will create a baseline.

If replay says there is nothing to replay, the item has only edited or baseline versions. Replay works from an imported version alone.

If replay says the stored record could not be read as MARC, the file is still kept unchanged and can be downloaded from the history page for inspection.

Re-importing a record that matches an existing item can add a baseline version as well as the import version. This is expected: it preserves the catalogue state that the import is about to change.
