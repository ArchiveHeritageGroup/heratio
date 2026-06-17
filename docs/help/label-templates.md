# Label Templates - User Guide

Configurable label / barcode **sheet templates** for batch printing (e.g. Avery-style sheets). Previously the batch label layout was fixed; now you can define reusable presets - page size, grid, label dimensions, what to show, and the barcode/QR source - and mark one as the default.

Manage them at **Admin -> Label Templates** (`/admin/label/templates`).

## What a template defines

| Setting | Meaning |
|---|---|
| Page size | A4 or Letter |
| Columns / Rows | Labels across and down the sheet |
| Label width / height (mm) | Physical size of each label |
| Page margin (mm) | Outer margin of the printed sheet |
| Gutter (mm) | Gap between labels |
| Font size (pt) | Base text size on the label |
| Show title / identifier / repository | Which fields print |
| Show barcode + barcode source | Whether to print a barcode, and from which field (identifier / accession / call_number / isbn) |
| Show QR + QR target | Whether to print a QR code, pointing at the record URL or its identifier |
| Default | The template batch printing uses when none is chosen |

A default **Avery L7159 (A4, 3 x 8, 63.5 x 33.9 mm)** template ships out of the box.

## How it's used

When you batch-print labels, the selected template (or the default) drives the printed sheet: the page is laid out as a real `columns x rows` grid at the exact millimetre dimensions, so labels line up with the physical sheet in your printer. The per-template show-flags and barcode source are applied unless overridden for that print run.

## Notes

- Marking a template as **Default** automatically clears the default flag on the others (only one default at a time).
- Templates are additive presets - editing or deleting one never changes already-printed output.

Source: PSIS `ahgLabelPlugin` parity (issue #1281).
