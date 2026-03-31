# Barcode System

## User Guide

Generate, print, and scan barcodes for tracking physical items, boxes, and locations in your archive.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                    BARCODE SYSTEM                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  GENERATE              PRINT                SCAN            │
│     │                    │                    │             │
│     ▼                    ▼                    ▼             │
│  Create codes       Label sheets        Find records        │
│  for records        for boxes           Update locations    │
│  and locations      and items           Track movements     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Barcode Types
```
┌─────────────────────────────────────────────────────────────┐
│  BARCODE FORMATS                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ║║│║║│║║│║║│║║│║                                          │
│  CODE 128         - Standard linear barcode                 │
│                     Best for: Reference codes               │
│                                                             │
│  ▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄                                          │
│  ██ ▄▄▄▄▄ ██▄█ █                                          │
│  QR CODE          - 2D matrix barcode                       │
│                     Best for: URLs, detailed info           │
│                                                             │
│  CODE 39          - Alphanumeric barcode                    │
│                     Best for: Simple identifiers            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Access
```
  Main Menu
      │
      ▼
   Admin
      │
      ▼
   Tools
      │
      ▼
   Barcodes ──────────────────────────────────────────────────┐
      │                                                        │
      ├──▶ Generate Barcodes    (create new barcodes)          │
      │                                                        │
      ├──▶ Print Labels         (create label sheets)          │
      │                                                        │
      ├──▶ Scan                 (lookup by barcode)            │
      │                                                        │
      └──▶ Batch Print          (print multiple)               │
```

---

## Generating Barcodes

### For a Single Record

From any record view, click **More** → **Generate Barcode**:
```
┌─────────────────────────────────────────────────────────────┐
│  GENERATE BARCODE                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Record:          ADM/BOARD/1985/001                        │
│  Title:           Board Meeting Minutes 1985                │
│                                                             │
│  BARCODE OPTIONS                                            │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Format:          [Code 128              ▼]                 │
│                   • Code 128                                │
│                   • QR Code                                 │
│                   • Code 39                                 │
│                                                             │
│  Content:         [Reference Code        ▼]                 │
│                   • Reference Code                          │
│                   • Accession Number                        │
│                   • System ID                               │
│                   • Custom                                  │
│                                                             │
│  Include Text:    [✓] Show human-readable text below        │
│                                                             │
│                                                             │
│  PREVIEW                                                    │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│         ║║│║║│║║│║║│║║│║║│║║│║║│║                          │
│           ADM/BOARD/1985/001                                │
│                                                             │
│              [Download]    [Print]                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### For Locations

Generate barcodes for storage locations:
```
┌─────────────────────────────────────────────────────────────┐
│  LOCATION BARCODE                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Location:        Strong Room A, Shelf 3, Bay 2             │
│  Code:            LOC-SRA-S03-B02                           │
│                                                             │
│         ▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄                                    │
│         ██ ▄▄▄▄▄ ██▄█ █                                    │
│         █▄ █   █ █▄▄▄██                                    │
│         ██ █▄▄▄█ █ ▄▄ █                                    │
│         ▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀                                    │
│                                                             │
│         Strong Room A                                       │
│         Shelf 3, Bay 2                                      │
│                                                             │
│              [Download]    [Print]                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Printing Labels

### Single Label
```
┌─────────────────────────────────────────────────────────────┐
│  PRINT LABEL                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Label Size:      [Standard (50mm x 25mm)  ▼]               │
│                   • Small (25mm x 10mm)                     │
│                   • Standard (50mm x 25mm)                  │
│                   • Large (100mm x 50mm)                    │
│                   • Custom                                  │
│                                                             │
│  Include:         [✓] Barcode                               │
│                   [✓] Reference Code                        │
│                   [✓] Title (truncated)                     │
│                   [ ] Date                                  │
│                   [ ] Repository                            │
│                                                             │
│  Copies:          [1    ]                                   │
│                                                             │
│              [Preview]    [Print]                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Batch Label Printing

Print multiple labels at once:
```
┌─────────────────────────────────────────────────────────────┐
│  BATCH PRINT LABELS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SELECT RECORDS                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Source:          [Search Results          ▼]               │
│                   • Search Results                          │
│                   • Selected Records                        │
│                   • By Reference Range                      │
│                   • By Location                             │
│                   • Import List (CSV)                       │
│                                                             │
│  Reference Range: [ADM/001    ] to [ADM/100    ]           │
│                                                             │
│  Records Found:   87                                        │
│                                                             │
│  LABEL SETTINGS                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  Label Stock:     [Avery L7163 (14 per sheet) ▼]           │
│                                                             │
│  Start Position:  [1    ] (skip used labels)                │
│                                                             │
│              [Preview PDF]    [Print]                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Label Sheet Preview
```
┌─────────────────────────────────────────────────────────────┐
│  LABEL SHEET PREVIEW                           Page 1 of 7  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────┐  ┌───────────────┐                      │
│  │ ║║│║║│║║│║║│  │  │ ║║│║║│║║│║║│  │                      │
│  │ ADM/001       │  │ ADM/002       │                      │
│  │ Board Minutes │  │ Annual Report │                      │
│  └───────────────┘  └───────────────┘                      │
│                                                             │
│  ┌───────────────┐  ┌───────────────┐                      │
│  │ ║║│║║│║║│║║│  │  │ ║║│║║│║║│║║│  │                      │
│  │ ADM/003       │  │ ADM/004       │                      │
│  │ Correspondence│  │ Accounts      │                      │
│  └───────────────┘  └───────────────┘                      │
│                                                             │
│  ... (14 labels per sheet)                                  │
│                                                             │
│  [◀ Prev]  [Download PDF]  [Print All]  [Next ▶]           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Scanning Barcodes

### Using a Scanner
```
┌─────────────────────────────────────────────────────────────┐
│  BARCODE LOOKUP                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Scan or Enter Barcode:                                     │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ADM/BOARD/1985/001                              🔍  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Tip: Place cursor in box, then scan with barcode reader   │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  RESULT                                                     │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  ✅ Record Found                                            │
│                                                             │
│  Title:           Board Meeting Minutes 1985                │
│  Reference:       ADM/BOARD/1985/001                        │
│  Level:           File                                      │
│  Location:        Strong Room A, Shelf 3                    │
│                                                             │
│  [View Record]  [Update Location]  [Print Label]            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Using Mobile Camera

On mobile devices, use the camera to scan:
```
┌─────────────────────────────────────────────────────────────┐
│  MOBILE SCANNER                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │                                                     │   │
│  │           Point camera at barcode                   │   │
│  │                                                     │   │
│  │              ┌─────────────┐                        │   │
│  │              │             │                        │   │
│  │              │   [ /// ]   │  ← Align barcode here  │   │
│  │              │             │                        │   │
│  │              └─────────────┘                        │   │
│  │                                                     │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [📷 Scan]    [⌨️ Enter Manually]    [🔦 Toggle Flash]      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Location Tracking with Barcodes

### Update Location by Scanning

Quickly move items between locations:
```
┌─────────────────────────────────────────────────────────────┐
│  LOCATION UPDATE                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Step 1: Scan LOCATION barcode                              │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  ✅ Location: Strong Room B, Shelf 5, Bay 3                 │
│                                                             │
│  Step 2: Scan ITEM barcodes                                 │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  ✅ ADM/BOARD/1985/001 - Board Meeting Minutes              │
│  ✅ ADM/BOARD/1985/002 - Annual Report                      │
│  ✅ ADM/BOARD/1985/003 - Financial Statements               │
│  🔄 Waiting for next scan...                                │
│                                                             │
│  Items scanned: 3                                           │
│                                                             │
│  [Finish & Save]    [Clear All]    [Undo Last]              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Workflow
```
┌─────────────────────────────────────────────────────────────┐
│  LOCATION UPDATE WORKFLOW                                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│       ┌──────────────┐                                     │
│       │ Scan Location│                                     │
│       │   Barcode    │                                     │
│       └──────┬───────┘                                     │
│              │                                             │
│              ▼                                             │
│       ┌──────────────┐                                     │
│       │  Scan Item   │◄────────┐                           │
│       │   Barcode    │         │                           │
│       └──────┬───────┘         │                           │
│              │                 │                           │
│              ▼                 │                           │
│       ┌──────────────┐         │                           │
│       │ More items?  │─── Yes ─┘                           │
│       └──────┬───────┘                                     │
│              │ No                                          │
│              ▼                                             │
│       ┌──────────────┐                                     │
│       │    Save      │                                     │
│       │   Changes    │                                     │
│       └──────────────┘                                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Box Barcodes

Create barcodes for archival boxes:
```
┌─────────────────────────────────────────────────────────────┐
│  BOX BARCODE                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Box Number:      BOX-2025-0142                             │
│                                                             │
│  Contents:        ADM/BOARD/1985/001 - 1985/025             │
│                   (25 files)                                │
│                                                             │
│  Location:        Strong Room A, Shelf 3                    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │         ║║│║║│║║│║║│║║│║║│║║│║║│║                   │   │
│  │              BOX-2025-0142                          │   │
│  │                                                     │   │
│  │         Board Meeting Minutes                       │   │
│  │         1985/001 - 1985/025                         │   │
│  │         Strong Room A, Shelf 3                      │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [Print Box Label]    [Print Spine Label]                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Barcode Settings

Configure barcode defaults:
```
┌─────────────────────────────────────────────────────────────┐
│  BARCODE SETTINGS                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  DEFAULT FORMAT                                             │
│  ─────────────────────────────────────────────────────────  │
│  Barcode Type:    [Code 128              ▼]                 │
│  Content Field:   [Reference Code        ▼]                 │
│  Include Text:    [✓] Yes                                   │
│                                                             │
│  LABEL DEFAULTS                                             │
│  ─────────────────────────────────────────────────────────  │
│  Label Stock:     [Avery L7163            ▼]                │
│  Font:            [Arial                  ▼]                │
│  Font Size:       [8pt                    ▼]                │
│                                                             │
│  PREFIX SETTINGS                                            │
│  ─────────────────────────────────────────────────────────  │
│  Location Prefix: [LOC-                    ]                │
│  Box Prefix:      [BOX-                    ]                │
│                                                             │
│              [Save Settings]                                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Supported Label Stocks
```
┌─────────────────────────────────────────────────────────────┐
│  LABEL TEMPLATES                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Standard Labels:                                           │
│  • Avery L7163 (14 per sheet, 99.1 x 38.1mm)               │
│  • Avery L7161 (18 per sheet, 63.5 x 46.6mm)               │
│  • Avery L7651 (65 per sheet, 38.1 x 21.2mm)               │
│                                                             │
│  Box Labels:                                                │
│  • Avery L7167 (1 per sheet, 199.6 x 289.1mm)              │
│  • Avery L7165 (8 per sheet, 99.1 x 67.7mm)                │
│                                                             │
│  Spine Labels:                                              │
│  • Custom spine (50 x 200mm)                                │
│                                                             │
│  [+ Add Custom Template]                                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Tips
```
┌────────────────────────────────────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                  │
├────────────────────────────────┼────────────────────────────┤
│  Use consistent barcode format │  Mix different formats     │
│  Test labels before bulk print │  Print hundreds untested   │
│  Include human-readable text   │  Use barcode-only labels   │
│  Label boxes and locations     │  Only label items          │
│  Keep scanner charged          │  Let battery die mid-task  │
│  Back up barcode data          │  Assume database is enough │
└────────────────────────────────┴────────────────────────────┘
```

---

## Troubleshooting
```
Problem                          Solution
───────────────────────────────────────────────────────────
Barcode won't scan            →  Clean the barcode
                                 Improve lighting
                                 Check for damage
                                 Try manual entry
                                 
Wrong record appears          →  Duplicate barcode exists
                                 Check reference code
                                 Report to administrator
                                 
Labels printing misaligned    →  Check paper orientation
                                 Adjust printer settings
                                 Try different start position
                                 
Scanner not detected          →  Check USB connection
                                 Install scanner drivers
                                 Try different USB port
                                 
QR code too small             →  Use larger label size
                                 Reduce data in QR code
```

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the AtoM AHG Framework*
