# Library Module

## User Guide

Catalog library materials using Dublin Core and MARC-based fields for books, journals, and other published materials.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                      LIBRARY MODULE                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📚 Books         📰 Journals      📀 Media                 │
│     │                │                │                     │
│     ▼                ▼                ▼                     │
│  Monographs      Serials          CDs/DVDs                  │
│  Collections     Periodicals      Audiobooks                │
│  Reference       Magazines        E-resources               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_9c8d7d07.png)
```

---

## When to Use Library Module
```
┌─────────────────────────────────────────────────────────────┐
│  USE LIBRARY MODULE FOR:                                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📕 Published books and monographs                          │
│  📰 Journals, magazines, and serials                        │
│  📖 Reference materials (encyclopedias, dictionaries)       │
│  💿 Audio/visual materials (CDs, DVDs, audiobooks)          │
│  📄 Pamphlets and ephemera                                  │
│  🗺️  Maps and atlases                                       │
│  🎵 Sheet music and scores                                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_1e7c048d.png)
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
   Library ──────────────────────────────────────────────────┐
      │                                                       │
      ├──▶ Browse Library       (view all items)              │
      │                                                       │
      ├──▶ Add Library Item     (create new record)           │
      │                                                       │
      └──▶ Import MARC          (bulk import)                 │
```

---

## Adding a Library Item

### Step 1: Click Add Library Item

Go to **GLAM/DAM** → **Library** → **Add**

### Step 2: Choose Material Type
```
┌─────────────────────────────────────────────────────────────┐
│  SELECT MATERIAL TYPE                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ○ Book / Monograph                                         │
│  ○ Journal / Serial                                         │
│  ○ Article                                                  │
│  ○ Audio Recording                                          │
│  ○ Video Recording                                          │
│  ○ Map                                                      │
│  ○ Music Score                                              │
│  ○ Electronic Resource                                      │
│  ○ Other                                                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_2ca4086c.png)
```

### Step 3: Fill in the Form
```
┌─────────────────────────────────────────────────────────────┐
│  ADD LIBRARY ITEM                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  TITLE INFORMATION                                          │
│  ─────────────────────────────────────────────────────────  │
│  Title:           [A History of South Africa      ]         │
│  Subtitle:        [From 1652 to Present           ]         │
│  Uniform Title:   [                               ]         │
│                                                             │
│  CREATOR INFORMATION                                        │
│  ─────────────────────────────────────────────────────────  │
│  Author:          [Thompson, Leonard              ]         │
│  Other Authors:   [+ Add another                  ]         │
│  Editor:          [                               ]         │
│                                                             │
│  PUBLICATION                                                │
│  ─────────────────────────────────────────────────────────  │
│  Publisher:       [Yale University Press          ]         │
│  Place:           [New Haven                      ]         │
│  Date:            [2014                           ]         │
│  Edition:         [4th edition                    ]         │
│                                                             │
│  IDENTIFIERS                                                │
│  ─────────────────────────────────────────────────────────  │
│  ISBN:            [978-0-300-20723-0              ]         │
│  Call Number:     [DT1787 .T56 2014               ]         │
│  Accession No:    [LIB-2025-00142                 ]         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_40dd9ead.png)
```

---

## Key Fields Explained

### Title Area
```
┌─────────────────────────────────────────────────────────────┐
│  FIELD             │  WHAT TO ENTER                         │
├────────────────────┼────────────────────────────────────────┤
│  Title             │  Main title of the work                │
│  Subtitle          │  Secondary title after colon           │
│  Uniform Title     │  Standard title for variants           │
│  Series Title      │  Name of book series                   │
│  Volume/Issue      │  Volume number or issue                │
└────────────────────┴────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_fac973fc.png)
```

### Creator Area
```
┌─────────────────────────────────────────────────────────────┐
│  FIELD             │  WHAT TO ENTER                         │
├────────────────────┼────────────────────────────────────────┤
│  Author            │  Primary creator (Last, First)         │
│  Editor            │  Person who edited the work            │
│  Translator        │  Person who translated                 │
│  Illustrator       │  Person who created illustrations      │
│  Corporate Author  │  Organization as author                │
└────────────────────┴────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_df296a0c.png)
```

### Physical Description
```
┌─────────────────────────────────────────────────────────────┐
│  FIELD             │  WHAT TO ENTER                         │
├────────────────────┼────────────────────────────────────────┤
│  Extent            │  Number of pages (e.g., "324 pages")   │
│  Dimensions        │  Size (e.g., "24 cm")                  │
│  Illustrations     │  Type (e.g., "color illustrations")    │
│  Accompanying      │  Included materials (e.g., "1 CD-ROM") │
└────────────────────┴────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_0c6ed4d9.png)
```

---

## Subject and Classification

### Adding Subjects
```
┌─────────────────────────────────────────────────────────────┐
│  SUBJECTS                                                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Subject Headings:                                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ South Africa -- History                             │   │
│  │ [×]                                                 │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Apartheid -- South Africa                           │   │
│  │ [×]                                                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [+ Add Subject]                                            │
│                                                             │
│  Genre/Form:       [History          ▼]                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_f1a22de4.png)
```

### Classification Numbers
```
┌─────────────────────────────────────────────────────────────┐
│  CLASSIFICATION                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Call Number:      [DT1787 .T56 2014        ]               │
│                                                             │
│  Dewey Decimal:    [968                     ]               │
│                                                             │
│  LC Classification:[DT1787                  ]               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_94376dd5.png)
```

---

## Cataloging Serials (Journals)

For journals and periodicals:
```
┌─────────────────────────────────────────────────────────────┐
│  SERIAL INFORMATION                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Title:           [South African Historical Journal]        │
│                                                             │
│  ISSN:            [0258-2473                       ]        │
│                                                             │
│  Frequency:       [Quarterly        ▼]                      │
│                   • Annual                                  │
│                   • Semi-annual                             │
│                   • Quarterly  ←                            │
│                   • Monthly                                 │
│                   • Weekly                                  │
│                                                             │
│  Holdings:        [Vol. 1 (1969) - Vol. 75 (2023)  ]        │
│                                                             │
│  Gaps:            [Vol. 45-47 missing             ]         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_0a7eab10.png)
```

---

## Browsing the Library

### Filter Options
```
┌─────────────────────────────────────────────────────────────┐
│  BROWSE LIBRARY                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Search: [                              ] [Search]          │
│                                                             │
│  Filter by:                                                 │
│  ┌────────────┐ ┌────────────┐ ┌────────────┐              │
│  │ All Types  │ │ All Dates  │ │ All Subjects│              │
│  │     ▼      │ │     ▼      │ │     ▼      │              │
│  └────────────┘ └────────────┘ └────────────┘              │
│                                                             │
│  Sort by: [Title A-Z           ▼]                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_7fb6ad1b.png)
```

### Search Tips
```
┌─────────────────────────────────────────────────────────────┐
│  SEARCH EXAMPLES                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  By Title:        "History of Cape Town"                    │
│  By Author:       author:Thompson                           │
│  By ISBN:         isbn:9780300207230                        │
│  By Call Number:  call:DT1787                               │
│  By Subject:      subject:apartheid                         │
│  By Date:         date:2014                                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_40266e44.png)
```

---

## Digital Objects

Attach digital files to library records:
```
┌─────────────────────────────────────────────────────────────┐
│  DIGITAL OBJECTS                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Attached Files:                                            │
│                                                             │
│  📄 Table_of_Contents.pdf        [View] [Delete]            │
│  📄 Cover_Image.jpg              [View] [Delete]            │
│                                                             │
│  [+ Upload File]                                            │
│                                                             │
│  Or link to external resource:                              │
│  URL: [https://...                          ]               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_70cee8f1.png)
```

---

## Tips for Cataloging
```
┌────────────────────────────────────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                  │
├────────────────────────────────┼────────────────────────────┤
│  Enter ISBN/ISSN               │  Skip identifiers         │
│  Use standard subject headings │  Make up your own terms   │
│  Include physical description  │  Leave extent blank       │
│  Add call numbers              │  Forget classification    │
│  Note condition issues         │  Ignore damage            │
│  Link related works            │  Catalog in isolation     │
└────────────────────────────────┴────────────────────────────┘
![wireframe](./images/wireframes/wireframe_9dd21dac.png)
```

---

## Need Help?

Contact your system administrator or cataloging librarian if you need assistance.

---

*Part of the AtoM AHG Framework*
