# Label Printing

## User Guide

Generate and print customizable labels with barcodes and QR codes for archival records, library items, museum objects, and gallery artworks.

---

## Overview
```
+-------------------------------------------------------------+
|                     LABEL GENERATOR                          |
+-------------------------------------------------------------+
|                                                             |
|   SELECT              CONFIGURE           OUTPUT            |
|      |                   |                  |               |
|      v                   v                  v               |
|   Choose             Customize          Print or           |
|   Record             Label Options      Download           |
|                                                             |
+-------------------------------------------------------------+
```

---

## Supported GLAM Sectors

The Label Generator automatically detects your record type and provides sector-appropriate identifiers:

```
+-------------------------------------------------------------+
|                    SECTOR DETECTION                          |
+-------------------------------------------------------------+
|                                                             |
|  ARCHIVE       - Archival records with identifiers          |
|  LIBRARY       - Books/serials with ISBN, ISSN, Call Number |
|  MUSEUM        - Objects with accession/object numbers      |
|  GALLERY       - Artworks with identifiers                  |
|                                                             |
+-------------------------------------------------------------+
```

---

## How to Access

### From a Record

1. Navigate to any record in the system
2. Access the label generator via URL: `/label/[record-slug]`

```
  Record View
      |
      v
   /label/[slug]
      |
      v
   Label Configuration --------------------------------+
      |                                                |
      +---> Configure Options                          |
      |                                                |
      +---> Preview Label                              |
      |                                                |
      +---> Print or Download                          |
```

---

## Label Components

### What's Included on a Label

```
+-------------------------------------------------------------+
|                    LABEL COMPONENTS                          |
+-------------------------------------------------------------+
|                                                             |
|  TITLE           - Record title (toggleable)                |
|  REPOSITORY      - Institution name (toggleable)            |
|  LINEAR BARCODE  - Code 128 barcode (toggleable)            |
|  QR CODE         - Links to record URL (toggleable)         |
|                                                             |
+-------------------------------------------------------------+
```

---

## Barcode Sources

Select which identifier to encode in the barcode:

### Archive Records
| Source | Description |
|--------|-------------|
| Identifier | Primary reference code |
| Title | Record title (fallback) |

### Library Items
| Source | Description |
|--------|-------------|
| ISBN | International Standard Book Number |
| ISSN | International Standard Serial Number |
| LCCN | Library of Congress Control Number |
| OpenLibrary ID | OpenLibrary identifier |
| Barcode | Library barcode number |
| Call Number | Shelf location code |

### Museum Objects
| Source | Description |
|--------|-------------|
| Accession Number | Museum accession number |
| Object Number | Catalogue object number |

---

## Step-by-Step: Printing a Label

### Step 1: Open the Label Generator

Navigate to `/label/[record-slug]` where `[record-slug]` is the URL slug of your record.

### Step 2: Select Barcode Source

Choose which identifier to encode:

```
+-------------------------------------------------------------+
|  BARCODE SOURCE                                              |
+-------------------------------------------------------------+
|                                                             |
|  [Select Source                                      v]     |
|     - ISBN: 978-0-12345-678-9                               |
|     - Call Number: QA76.73.P98                              |
|     - Identifier: LIB-2024-001                              |
|     - Title: Introduction to Programming                    |
|                                                             |
+-------------------------------------------------------------+
```

### Step 3: Configure Label Size

```
+-------------------------------------------------------------+
|  LABEL SIZE                                                  |
+-------------------------------------------------------------+
|                                                             |
|  Small (50mm)   - Compact labels for small items            |
|  Medium (75mm)  - Standard labels (default)                 |
|  Large (100mm)  - Large labels for boxes/folders            |
|                                                             |
+-------------------------------------------------------------+
```

### Step 4: Toggle Display Options

```
+-------------------------------------------------------------+
|  DISPLAY OPTIONS                                             |
+-------------------------------------------------------------+
|                                                             |
|  [x] Linear Barcode    Show Code 128 barcode                |
|  [x] QR Code           Show QR code linking to record       |
|  [x] Title             Include record title                 |
|  [x] Repository        Include institution name             |
|                                                             |
+-------------------------------------------------------------+
```

### Step 5: Preview and Print

```
+-------------------------------------------------------------+
|  PREVIEW                                                     |
+-------------------------------------------------------------+
|  +------------------------+                                 |
|  |   Meeting Minutes      |                                 |
|  |   1985-1990            |                                 |
|  |                        |                                 |
|  |   National Archives    |                                 |
|  |                        |                                 |
|  |   |||||||||||||||||||  |                                 |
|  |   ADM-BOARD-1985-001   |                                 |
|  |                        |                                 |
|  |   [QR Code Image]      |                                 |
|  +------------------------+                                 |
|                                                             |
|  [Back]  [Print Label]  [Download PNG]                      |
+-------------------------------------------------------------+
```

---

## Output Options

### Print Label

Click **Print Label** to open the browser print dialog:
- Labels are optimized for print with hidden navigation elements
- Works with standard label printers and sheet printers

### Download PNG

Click **Download PNG** to save the label as an image file:
- Requires html2canvas library
- File named: `label-[record-slug].png`

---

## Label Sizes Reference

| Size | Width | Best For |
|------|-------|----------|
| Small | 50mm (200px) | Small items, folders, CDs |
| Medium | 75mm (300px) | Standard boxes, books |
| Large | 100mm (400px) | Large boxes, oversized items |

---

## Barcode Types

### Linear Barcode (Code 128)

```
|||||||||||||||||||||||||||||||
ADM-BOARD-1985-001
```

- Industry-standard barcode format
- Scannable with handheld barcode readers
- Encodes selected identifier

### QR Code

```
+----------------+
| ## ## ## ## ## |
| ##    ##    ## |
| ## ## ## ## ## |
|    ##    ##    |
| ## ## ## ## ## |
+----------------+
```

- Contains URL link to the record
- Scannable with smartphones
- Provides quick access to full record details

---

## Sector-Specific Behavior

### Library Items

When a record has library metadata (ISBN, ISSN, etc.), the system:
- Auto-detects as "Library Item"
- Prioritizes ISBN as default barcode source
- Shows all available library identifiers

### Museum Objects

When a record has museum metadata, the system:
- Auto-detects as "Museum Object"
- Prioritizes Accession Number as default barcode source
- Shows object numbers and accession data

### Archive Records

Default behavior for standard archival descriptions:
- Uses Identifier as primary barcode source
- Falls back to Title if no identifier exists

---

## Common Uses

```
+-------------------------------------------------------------+
|                    USE LABEL GENERATOR FOR:                  |
+-------------------------------------------------------------+
|  Box Labels         - Identify storage containers            |
|  Folder Labels      - Mark individual folders                |
|  Spine Labels       - Library book spines                    |
|  Item Tags          - Attach to physical objects             |
|  Location Labels    - Mark shelving locations                |
|  Asset Tags         - Track digital media carriers           |
+-------------------------------------------------------------+
```

---

## Tips for Best Results

```
+--------------------------------+--------------------------------+
|  DO                            |  DON'T                         |
+--------------------------------+--------------------------------+
|  Test print on plain paper     |  Print directly to labels      |
|  first                         |  without testing               |
|                                |                                |
|  Use high-quality label stock  |  Use low-resolution printers   |
|                                |                                |
|  Match label size to physical  |  Use oversized labels for      |
|  item                          |  small items                   |
|                                |                                |
|  Include QR code for quick     |  Rely only on barcodes for     |
|  lookup                        |  detailed information          |
|                                |                                |
|  Ensure good contrast for      |  Print on colored backgrounds  |
|  scanning                      |                                |
+--------------------------------+--------------------------------+
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Barcode not scanning | Ensure adequate print resolution and contrast |
| QR code not working | Verify record URL is accessible |
| Download not working | Browser may need html2canvas library loaded |
| Wrong identifier shown | Select correct source from dropdown |
| Label too large/small | Adjust label size setting |

---

## Print Settings Recommendations

### Desktop Printers
- Paper size: A4 or Letter
- Quality: High or Best
- Margins: Minimum

### Label Printers
- Compatible with Dymo, Brother, Zebra
- Select appropriate label size
- Enable cut after print if available

---

## Need Help?

Contact your system administrator if you experience issues with label generation or printing.

---

*Part of the AtoM AHG Framework*
