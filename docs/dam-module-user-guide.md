# Digital Asset Management (DAM) Module

## User Guide

Manage born-digital and digitized assets including photographs, videos, audio files, documents, and 3D models.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                      DAM MODULE                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📷 Images        🎬 Video         🎵 Audio                 │
│     │                │                │                     │
│     ▼                ▼                ▼                     │
│  JPEG, TIFF      MP4, MOV        WAV, MP3                   │
│  PNG, RAW        AVI, MKV        FLAC, OGG                  │
│                                                             │
│  📄 Documents     🎲 3D Models     📊 Data                  │
│     │                │                │                     │
│     ▼                ▼                ▼                     │
│  PDF, DOCX       GLB, OBJ        CSV, XML                   │
│  TXT, RTF        STL, FBX        JSON                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## When to Use DAM Module
```
┌─────────────────────────────────────────────────────────────┐
│  USE DAM MODULE FOR:                                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📷 Digital photographs and scans                           │
│  🎬 Video recordings and films                              │
│  🎵 Audio recordings (oral history, music)                  │
│  📄 Born-digital documents                                  │
│  🎲 3D scans and models                                     │
│  📊 Datasets and spreadsheets                               │
│  🌐 Web archives and social media                           │
│  💾 Software and code                                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## How to Access
```
  Main Menu
      │
      ▼
   GLAM/DAM
      │
      ▼
   Digital Assets ────────────────────────────────────────────┐
      │                                                        │
      ├──▶ Browse Assets       (view all digital objects)      │
      │                                                        │
      ├──▶ Add Asset           (upload new files)              │
      │                                                        │
      ├──▶ Batch Upload        (multiple files at once)        │
      │                                                        │
      └──▶ Storage Report      (disk usage statistics)         │
```

---

## Adding a Digital Asset

### Step 1: Click Add Asset

Go to **GLAM/DAM** → **Digital Assets** → **Add**

### Step 2: Upload Your File
```
┌─────────────────────────────────────────────────────────────┐
│  UPLOAD DIGITAL ASSET                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │         Drag and drop files here                    │   │
│  │                                                     │   │
│  │              or click to browse                     │   │
│  │                                                     │   │
│  │         Maximum file size: 2 GB                     │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Selected: photograph_001.tif (45.2 MB)                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step 3: Auto-Detected Metadata

The system extracts metadata automatically:
```
┌─────────────────────────────────────────────────────────────┐
│  EXTRACTED METADATA                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ✅ File Information                                        │
│     Format:        TIFF                                     │
│     Size:          45.2 MB                                  │
│     Dimensions:    4000 × 3000 pixels                       │
│     Color Space:   sRGB                                     │
│     Bit Depth:     24-bit                                   │
│                                                             │
│  ✅ EXIF Data                                               │
│     Camera:        Canon EOS 5D Mark IV                     │
│     Date Taken:    2025-01-10 14:32:15                      │
│     ISO:           400                                      │
│     Aperture:      f/8                                      │
│     Shutter:       1/250s                                   │
│     GPS:           -33.9249, 18.4241                        │
│                                                             │
│  ✅ IPTC Data                                               │
│     Creator:       John Smith                               │
│     Copyright:     © 2025 Archive                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step 4: Add Descriptive Metadata
```
┌─────────────────────────────────────────────────────────────┐
│  DESCRIPTIVE METADATA                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  IDENTIFICATION                                             │
│  ─────────────────────────────────────────────────────────  │
│  Asset ID:        [DAM-2025-00456            ] (auto)       │
│  Title:           [Company Board Building - Exterior]       │
│  Asset Type:      [Photograph               ▼]              │
│                                                             │
│  DESCRIPTION                                                │
│  ─────────────────────────────────────────────────────────  │
│  Description:                                               │
│  [Front elevation of the Company Board Building,           ]│
│  [photographed from Adderley Street showing the            ]│
│  [neoclassical facade and entrance portico.                ]│
│                                                             │
│  CREATION                                                   │
│  ─────────────────────────────────────────────────────────  │
│  Creator:         [Smith, John               ]              │
│  Date Created:    [2025-01-10                ] (from EXIF)  │
│  Place Created:   [Cape Town, South Africa   ]              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Supported File Types

### Images
```
┌─────────────────────────────────────────────────────────────┐
│  IMAGE FORMATS                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Preservation:    TIFF, DNG, RAW                            │
│  Access:          JPEG, PNG, GIF, WebP                      │
│  Vector:          SVG, AI, EPS                              │
│                                                             │
│  ⚙️ System generates:                                       │
│     • Thumbnail (150px)                                     │
│     • Reference (800px)                                     │
│     • Access copy (2000px JPEG)                             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Audio
```
┌─────────────────────────────────────────────────────────────┐
│  AUDIO FORMATS                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Preservation:    WAV, FLAC, AIFF                           │
│  Access:          MP3, OGG, AAC, M4A                        │
│                                                             │
│  ⚙️ System extracts:                                        │
│     • Duration                                              │
│     • Sample rate                                           │
│     • Bit depth                                             │
│     • Channels (mono/stereo)                                │
│     • Waveform visualization                                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Video
```
┌─────────────────────────────────────────────────────────────┐
│  VIDEO FORMATS                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Preservation:    MOV, MKV, AVI (uncompressed)              │
│  Access:          MP4 (H.264), WebM                         │
│                                                             │
│  ⚙️ System extracts:                                        │
│     • Duration                                              │
│     • Resolution                                            │
│     • Frame rate                                            │
│     • Codec information                                     │
│     • Thumbnail frames                                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 3D Models
```
┌─────────────────────────────────────────────────────────────┐
│  3D MODEL FORMATS                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Web Display:     GLB, GLTF                                 │
│  Exchange:        OBJ, FBX, STL                             │
│  Apple AR:        USDZ                                      │
│                                                             │
│  ⚙️ System generates:                                       │
│     • Thumbnail render                                      │
│     • Web-optimized GLB                                     │
│     • Polygon count                                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## IPTC Metadata

Standard fields for images:
```
┌─────────────────────────────────────────────────────────────┐
│  IPTC FIELDS                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  CONTENT                                                    │
│  Headline:        [Board Building Exterior    ]             │
│  Description:     [Front view showing...      ]             │
│  Keywords:        [architecture, heritage, Cape Town]       │
│                                                             │
│  ORIGIN                                                     │
│  Creator:         [John Smith                 ]             │
│  Date Created:    [2025-01-10                 ]             │
│  City:            [Cape Town                  ]             │
│  Country:         [South Africa               ]             │
│                                                             │
│  RIGHTS                                                     │
│  Copyright:       [© 2025 The Archive         ]             │
│  Rights Usage:    [Contact archive for use    ]             │
│  Credit Line:     [Photo: John Smith/Archive  ]             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Batch Upload

Upload multiple files at once:

### Step 1: Prepare Files
```
┌─────────────────────────────────────────────────────────────┐
│  BATCH UPLOAD                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Drop multiple files or a folder:                           │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │         Drop files or folder here                   │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Files queued: 25                                           │
│                                                             │
│  ☑ photo_001.tif    45 MB     Ready                        │
│  ☑ photo_002.tif    42 MB     Ready                        │
│  ☑ photo_003.tif    48 MB     Ready                        │
│  ...                                                        │
│                                                             │
│  [Start Upload]  [Clear Queue]                              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step 2: Apply Common Metadata
```
┌─────────────────────────────────────────────────────────────┐
│  APPLY TO ALL                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Apply these values to all uploaded files:                  │
│                                                             │
│  Creator:         [John Smith                 ]             │
│  Copyright:       [© 2025 The Archive         ]             │
│  Repository:      [Main Collection           ▼]             │
│                                                             │
│  ☑ Extract EXIF data automatically                         │
│  ☑ Generate access copies                                  │
│  ☐ Apply to existing parent record                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Technical Metadata

View detailed file information:
```
┌─────────────────────────────────────────────────────────────┐
│  TECHNICAL METADATA                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  FILE CHARACTERISTICS                                       │
│  ─────────────────────────────────────────────────────────  │
│  Filename:        photograph_001.tif                        │
│  MIME Type:       image/tiff                                │
│  File Size:       45,234,567 bytes (45.2 MB)               │
│  Checksum (MD5):  a3f2b8c9d4e5f6g7h8i9j0k1l2m3n4o5          │
│  Checksum (SHA256): abc123...                               │
│                                                             │
│  IMAGE PROPERTIES                                           │
│  ─────────────────────────────────────────────────────────  │
│  Width:           4000 pixels                               │
│  Height:          3000 pixels                               │
│  Resolution:      300 DPI                                   │
│  Color Space:     sRGB IEC61966-2.1                        │
│  Bit Depth:       24-bit (8 bits per channel)              │
│  Compression:     None (uncompressed)                       │
│                                                             │
│  PRESERVATION                                               │
│  ─────────────────────────────────────────────────────────  │
│  Format Valid:    ✅ Yes                                    │
│  Well-formed:     ✅ Yes                                    │
│  PRONOM ID:       fmt/353                                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Storage and Derivatives

How files are stored:
```
┌─────────────────────────────────────────────────────────────┐
│  STORAGE STRUCTURE                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Original File (Preservation)                               │
│      │                                                      │
│      ├── Master copy (never modified)                       │
│      │                                                      │
│      └── Derivatives (auto-generated)                       │
│              │                                              │
│              ├── Thumbnail  (150px)                         │
│              ├── Reference  (800px)                         │
│              └── Access     (2000px)                        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Rights and Access

Control who can view and download:
```
┌─────────────────────────────────────────────────────────────┐
│  ACCESS SETTINGS                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Visibility:      [Public                   ▼]              │
│                   • Public (anyone)                         │
│                   • Restricted (logged-in users)            │
│                   • Private (staff only)                    │
│                                                             │
│  Download:        [Access copy only         ▼]              │
│                   • Original (full resolution)              │
│                   • Access copy only                        │
│                   • No download                             │
│                   • Request required                        │
│                                                             │
│  Watermark:       [✓] Apply watermark to downloads          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Tips for Digital Assets
```
┌────────────────────────────────────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                  │
├────────────────────────────────┼────────────────────────────┤
│  Upload preservation masters   │  Only keep compressed     │
│  Add descriptive metadata      │  Leave titles as filenames│
│  Include rights information    │  Skip copyright details   │
│  Use consistent naming         │  Use random file names    │
│  Verify checksums              │  Assume files are intact  │
│  Tag with keywords             │  Skip subject access      │
└────────────────────────────────┴────────────────────────────┘
```

---

## Film & Video Metadata (New)

The DAM module includes specialized fields for film and video heritage materials.

### Production Details
```
┌─────────────────────────────────────────────────────────────┐
│  PRODUCTION DETAILS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Running Time:      [21         ] minutes                   │
│  Production Country:[South Africa        ]                  │
│  Country Code:      [ZAF] (ISO 3166-1 alpha-3)             │
│                                                             │
│  Production Company:[African Film Productions]              │
│  Distributor:       [                        ]              │
│  Series Title:      [                        ]              │
│  Season/Episode:    [   ] / [   ]                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Alternative Versions
Track different language versions, restorations, and edits:
```
┌─────────────────────────────────────────────────────────────┐
│  ALTERNATIVE VERSIONS                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Title              Type       Language    Code   Year      │
│  ─────────────────────────────────────────────────────────  │
│  Kuddes van die veld Language   Afrikaans   afr    1954     │
│  Flocks of the veld  Language   English     eng    1954     │
│                                                             │
│  Types: Language, Format, Restoration, Director's Cut,      │
│         Censored, Other                                     │
│                                                             │
│  [+ Add Version]                                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Format Holdings & Access
Document where physical copies are held:
```
┌─────────────────────────────────────────────────────────────┐
│  FORMAT HOLDINGS                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Format    Institution  Location    Condition  Access       │
│  ─────────────────────────────────────────────────────────  │
│  16mm      WCPLS        Film Vault  Good       On Request   │
│  35mm      NFVSA        Archive     Fair       Restricted   │
│  Digital   AHG          Cloud       Excellent  Available    │
│                                                             │
│  Format Types:                                              │
│  • Film: 35mm, 16mm, 8mm, Super8, Nitrate, Safety          │
│  • Video: VHS, Betacam, U-matic, DV                        │
│  • Digital: DVD, Blu-ray, Digital File, DCP, ProRes        │
│  • Audio: Audio Reel, Audio Cassette, Vinyl, CD            │
│                                                             │
│  Condition: Excellent, Good, Fair, Poor, Deteriorating     │
│  Access: Available, Restricted, Preservation Only,         │
│          Digitized, On Request, Staff Only                 │
│                                                             │
│  [+ Add Holding]                                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### External References
Link to film databases and resources:
```
┌─────────────────────────────────────────────────────────────┐
│  EXTERNAL REFERENCES                                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Type       URL                        Person      Role     │
│  ─────────────────────────────────────────────────────────  │
│  ESAT       esat.sun.ac.za/...         D. Swanson  Director │
│  IMDb       imdb.com/title/...                              │
│  Wikipedia  en.wikipedia.org/...                            │
│                                                             │
│  Link Types:                                                │
│  • South African: ESAT, SA Film, NFVSA                     │
│  • Film Databases: IMDb, BFI, AFI, Letterboxd, MUBI       │
│  • Knowledge Bases: Wikipedia, Wikidata, VIAF             │
│  • Media Platforms: YouTube, Vimeo, Archive.org           │
│  • Other: Review, Academic, Press                          │
│                                                             │
│  [+ Add Link]                                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Loan Management

The DAM module integrates with the Loan plugin for managing loans:

```
┌─────────────────────────────────────────────────────────────┐
│  LOAN ACTIONS                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📋 New Loan       Create a new loan for this asset         │
│  📊 Manage Loans   View and manage existing loans           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Optional Features

Some dashboard sections only appear when the corresponding plugins are enabled. If you don't see a feature listed below, ask your administrator to enable the plugin.

| Feature | Requires Plugin |
|---------|----------------|
| Loan Management (Manage Loans, New Loan Out) | ahgLoanPlugin |

---

## Need Help?

Contact your system administrator or digital archivist if you need assistance.

---

*Part of the AtoM AHG Framework*
*Last Updated: 2026-02-07*
