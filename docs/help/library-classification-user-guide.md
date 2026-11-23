> Heratio Help Center article. Category: Library.

# Classification Schedules

A classification schedule is a structured list of class numbers and captions that you load into Heratio. Once a schedule is active, cataloguers can browse it from the cataloguing form, keyed numbers are checked against it, and the call number report files items in true shelf order instead of plain alphabetical order.

Heratio works with Library of Congress, Dewey Decimal, Universal Decimal, local and other schemes. It does not ship with schedule content, so you load the schedule your library is licensed or entitled to use.

## Where to find it

Go to **Library Management > Reports** and choose **Classification Schedules** in the sidebar, or open `/library-manage/classification`. Viewing needs the read permission. Adding, changing and deleting need the create, update and delete permissions respectively.

## Create a schedule

1. Under **New schedule**, choose the **Scheme**.
2. Enter a **Name** and, if it applies, an **Edition**. A name can be used only once within a scheme.
3. Choose the **Validation** mode.
4. Add a **Description** if useful.
5. Tick **Active for this scheme** if this schedule should answer for the scheme now. Doing so replaces whichever schedule was active.
6. Choose **Create**.

Only one schedule is active per scheme, which lets you load a new edition and review it before switching over.

| Validation mode | Effect when a cataloguer saves a number |
| --- | --- |
| Off - do not check | No check is made |
| Warn - save and show a warning | The record saves and a warning names the problem |
| Strict - refuse unknown numbers | The save is refused until the number is corrected |

: Classification validation modes

A number that has not changed since the record was last saved is not re-checked, so switching to Strict will not block edits to older records that keep their existing number.

## Load entries from a CSV file

Open the schedule and use **Load from CSV**. The first row must name the columns.

| Column | Required | Purpose |
| --- | --- | --- |
| `notation` | Yes | The class number, up to 100 characters |
| `caption` | Yes | The class heading, up to 500 characters |
| `notation_end` | No | The end of a range |
| `parent_notation` | No | The broader entry, which must be in the file or the schedule |
| `note` | No | A scope note or other guidance |

: CSV columns for a classification schedule

The load is all or nothing. If any row is wrong, nothing is written and the page lists the problem lines. Quote any value containing a comma. The file limit is 20 MB.

Loading again updates existing notations, but only in the columns your file contains. Without `parent_notation`, a new row's broader entry is worked out from its notation and an existing entry keeps the one it has. Schedules arranged by ranges should supply `parent_notation`, because a range cannot be inferred from the number alone.

## Maintain entries by hand

Inside a schedule, the table shows one level at a time. Follow the number in the **Narrower** column to go down, use the breadcrumb to come back, or search by notation or caption.

To add an entry, go to the level where it belongs and complete **Add entry**. To change one, choose **Edit** on its row. An entry with narrower entries cannot be deleted until those are removed or moved.

Deleting a whole schedule removes all its entries. Catalogue records keep their call numbers; they simply lose their link to the deleted entry.

## Use a schedule while cataloguing

On the library item form, set the **Classification scheme**, then choose **Browse schedule**. Search or walk down the hierarchy and click a notation to place it in the record. For Dewey it goes into the Dewey field, and for other schemes into the call number field.

A message beside the button reports the result of the check. It can confirm the number is in the schedule, show the range it falls within, name the nearest broader class, or say the number is not in the schedule or not valid for that scheme.

## Shelf order

The **Call Numbers** report under Library Reports lists items in shelf order for their scheme, so a class such as QA76 files after QA9. A call number that does not fit its scheme is badged **Not in shelf order** and listed at the end. The report shows up to 500 items.

Shelf keys update when a record is saved. After a bulk load or anything else that bypasses the form, an administrator can rebuild them:

```bash
php artisan ahg:library-shelf-keys
```

## Troubleshooting

If the picker says there is no active schedule, open the schedule and tick **Active for this scheme** under Schedule settings.

If it asks you to choose a scheme first, set the classification scheme on the form. A record with only a Dewey number is treated as Dewey.

Take care when changing the scheme of a schedule that already has entries. Existing entries keep the notation and sort keys of the old scheme, so matches can go wrong. Create a new schedule under the right scheme and load the entries there instead.

Check ranges as you enter them, because Heratio does not currently reject a range whose end comes before its start. A reversed range such as QA80 to QA70 saves normally and then never matches anything.
