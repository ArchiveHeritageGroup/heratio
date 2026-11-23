> Heratio Help Center article. Category: Library.

# Printing Spine and Barcode Labels

Heratio prints spine labels, barcode labels and combined labels onto standard sheet stationery directly from your browser. You choose the sheet, name the copies, check a true-scale preview and print. Barcodes use Code 128.

Open **Library Management > Print Labels**, or go to `/library-manage/labels`. The page needs the update permission.

## Print a run of labels

1. Choose the **Stationery** that matches your label sheets.
2. Choose the **Label** format: Spine label, Barcode label, or Spine and barcode.
3. Say which copies to print. Scan or paste barcodes or accession numbers into **Selected copies**, one per line, or enter a **Whole accession**. You can use both.
4. If the first sheet is part used, enter the number of **Positions already used on the first sheet**.
5. Choose **Preview at true scale**. The preview opens in a new tab.
6. Read any warnings at the top of the preview, then choose **Print**.
7. In the print dialog, set the scale to 100% rather than "fit to page", and set margins to none.

A run can hold up to 2,000 labels. Beyond that, the preview tells you only the first 2,000 were taken, and the rest can go in another run.

For **Whole accession**, enter the exact number, or end it with `*` to take every copy numbered under it. For example, `2026/014-*` collects `2026/014-1`, `2026/014-2` and so on. A `*` on its own is refused, because it would select the entire collection.

## What each label carries

A spine label shows the call number broken into lines, followed by any call number suffix, volume designation and, for second and later copies, a copy number such as `c.2`. If the item has no call number, Heratio builds one from its classification or Dewey number and cutter.

A barcode label shows the Code 128 barcode. On labels at least 20 mm tall it also carries a shortened title. The combined format places the spine text and barcode side by side.

## Test before using real stock

Print a first run on plain paper. Tick **Print label outlines** so the label edges appear, then hold the sheet against your label stock up to the light.

If everything sits slightly off, use **Shift right (mm)** and **Shift down (mm)** to move the whole sheet by up to 10 mm in either direction. Negative values move it left or up. Printers differ, so note the values that work for each machine.

The preview includes a 100 mm reference bar. Measure it on the printed page to confirm nothing was scaled. Before committing a full batch, also read a few printed barcodes with the scanner your desk actually uses, since print quality and scanner settings both affect the result.

## Stationery templates

Five layouts are built in:

| Template | Labels per sheet |
| --- | --- |
| A4 - 21 labels, 63.5 x 38.1 mm | 21 |
| A4 - 24 labels, 63.5 x 33.9 mm | 24 |
| A4 - 14 labels, 99.1 x 38.1 mm | 14 |
| A4 - 65 labels, 38.1 x 21.2 mm | 65 |
| US Letter - 30 labels, 66.7 x 25.4 mm | 30 |

: Built-in label stationery templates

For any other stock, choose **Stationery Templates**, then **Add template**, or **Copy** on the closest existing layout. Built-in templates cannot be edited or deleted, so copying is the way to adjust one.

Measure your sheet and enter the page size, the top and left margins to the first label, the label width and height, the number of labels across and down, and the pitch. Pitch is the distance from one label's edge to the same edge of the next, so it includes any gap. Heratio refuses a layout whose labels would run off the page or overlap.

Deleting a custom template needs the delete permission and takes effect immediately, with no confirmation step.

## Troubleshooting

The preview explains anything it left out. **Not found** lists identifiers that match no copy. Copies from another branch are left out for staff working in a branch, and copies with no branch are reported separately so they can be assigned one.

The **No label for** list gives a reason per copy:

| Reason | What to do |
| --- | --- |
| no call number | Add a call number or classification to the item |
| call number too long for this label | Use a larger label |
| no barcode | Assign a barcode to the copy |
| barcode has characters Code 128 cannot carry | Correct the barcode value |
| barcode too wide to scan on this label | Use a wider label, or the Barcode label format |
| label too short for a barcode | Use a taller label |

: Reasons a label is not printed

Heratio declines these labels on purpose, so that you do not waste stock on something that will not scan or read.

If the page says label printing is not set up, the database migrations have not been run on this installation. Ask your administrator.
