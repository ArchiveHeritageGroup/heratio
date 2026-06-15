> Heratio Help Center article. Category: Tools.

# Labels and Barcodes

## A Guide for Cataloguers and Collection Staff

Print physical shelf, box, and item labels for any record in the catalogue, complete with a linear barcode, a QR code that links straight back to the record, the title, and the holding repository. Single labels and batch runs are both supported.

---

## Overview

The Labels tool turns any archival description, authority record, repository, or accession into a ready-to-print label. It reads the record's existing identifiers (call number, ISBN, ISSN, barcode, accession number, and so on), builds a scannable barcode and a QR code, and lays them out for a label printer or a sheet printer. You configure what appears, preview it live, then print or download.

Labels work across every collection sector. The tool detects whether a record is a library item, an archival record, a museum object, or a gallery artwork and labels it accordingly.

---

## Key features

- **Single-record labels.** Open a label page for one record straight from its catalogue entry.
- **Multiple barcode sources.** Choose what the barcode encodes: identifier, ISBN, ISSN, LCCN, OpenLibrary ID, library barcode, call number, accession number, or the title. The tool lists only the sources that the record actually has.
- **Automatic sector detection.** Library items expose library identifiers (ISBN, ISSN, call number); accessions expose the accession number; the label is tagged Library Item, Archival Record, Museum Object, or Gallery Artwork.
- **Linear barcode plus QR code.** The linear barcode encodes your chosen value. The QR code resolves to the record's public web address so anyone can scan straight to the catalogue entry.
- **Repository line.** The holding repository name is shown. For records that do not name a repository directly, the tool walks up the hierarchy to find the nearest ancestor that does.
- **Three label sizes.** Small (50 mm), Medium (75 mm), and Large (100 mm).
- **Show / hide controls.** Toggle the linear barcode, QR code, title, and repository line on or off independently.
- **Print or download.** Print directly (a print stylesheet hides all navigation so only the label prints) or download the label as a PNG image.
- **Batch printing.** Generate a printable sheet of labels for up to 100 records in one pass.

---

## How to use

### Print a single label

1. Open the record you want to label in the catalogue.
2. Go to the label page for that record at `/label/{slug}` (where `{slug}` is the record's address). The page title reads "Print Labels".
3. In the **Label Configuration** panel:
   - Pick a **Barcode Source** from the dropdown. The default is chosen automatically in the order ISBN, ISSN, barcode, accession number, identifier, then title.
   - Pick a **Label Size**: Small (50 mm), Medium (75 mm), or Large (100 mm).
   - Under **Show**, tick or untick **Linear Barcode** and **QR Code**.
   - Under **Include**, tick or untick **Title** and **Repository**.
4. Watch the **Preview** panel update live as you change options.
5. Click **Print Label** to send it to your printer, or **Download PNG** to save it as an image.
6. Use **Back to record** to return to the catalogue entry.

### Print labels in a batch

1. Submit a list of record addresses to the batch endpoint (`/label/batch-print`), for example from a selection or worksheet that collects record slugs.
2. The tool builds one label per record (up to 100 per run), each with its title, identifier, barcode, QR code, and repository.
3. The resulting page is a print-ready sheet. Use your browser's print command to print all labels at once.

---

## Configuration

- **Label size** is set per print job from the dropdown; there is no global default to configure.
- **Barcode source** defaults follow the preference order above, but you can override it for any single label or batch run.
- **Barcode and QR images** are rendered as images at print time. The QR code always points to the live record address, so scanning a printed label opens the current catalogue entry.
- **PNG download** uses an in-browser image capture. If the capture library is unavailable, use **Print Label** instead.
- Access requires you to be signed in. All label routes are behind authentication.

---

## References

- Source: `packages/ahg-label/`
- Issue: [GH #590](https://github.com/ArchiveHeritageGroup/heratio/issues/590)
