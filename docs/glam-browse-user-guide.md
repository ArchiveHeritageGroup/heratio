# GLAM Browse (Display Plugin)

## User Guide

Browse and view archival content with context-aware display modes optimized for Galleries, Libraries, Archives, and Museums.

---

## Overview
```
+---------------------------------------------------------------------+
|                          GLAM BROWSE                                 |
+---------------------------------------------------------------------+
|                                                                      |
|   ARCHIVE        MUSEUM         GALLERY       LIBRARY        DAM    |
|      |             |               |             |             |     |
|      V             V               V             V             V     |
|   Fonds        Objects        Artworks        Books        Photos   |
|   Series       Specimens      Paintings     Periodicals   Albums    |
|   Files        Artefacts      Drawings       Volumes       Slides   |
|   Items        Components     Editions       Chapters      Assets   |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## What is GLAM Browse?

GLAM Browse automatically detects what type of material you're viewing and displays it with the most appropriate layout and fields:

```
+---------------------------------------------------------------------+
|                      AUTO-DETECTION                                  |
+---------------------------------------------------------------------+
|                                                                      |
|   Your Content          Detected As          Displayed With         |
|       |                     |                     |                  |
|       V                     V                     V                  |
|   Fonds/Series   --->   Archive     --->   Hierarchy View           |
|   Museum Object  --->   Museum      --->   Spectrum Fields          |
|   Painting       --->   Gallery     --->   Artwork Gallery          |
|   Book/Journal   --->   Library     --->   Bibliographic View       |
|   Photograph     --->   DAM         --->   Photo Grid               |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## How to Access

### Main Browse Interface
```
  Main Menu
      |
      V
   Browse -------------------------+
      |                            |
      +---> GLAM Browse            |
      |     (/display/browse)      |
      |                            |
      +---> Filter by Type --------+
            |
            +---> Archive
            +---> Museum
            +---> Gallery
            +---> Library
            +---> DAM (Photos)
```

### Admin Dashboard (Staff Only)
```
  Main Menu
      |
      V
   Admin
      |
      V
   AHG Settings
      |
      V
   Display Plugin --------------------+
      |                               |
      +---> Dashboard      (stats)    |
      +---> Profiles       (layouts)  |
      +---> Levels         (hierarchy)|
      +---> Bulk Set Type  (batch)    |
      +---> Fields         (mapping)  |
      +---> Reindex        (search)   |
```

---

## Browse Interface

### Filter Panel
```
+---------------------------------------------------------------------+
|                         BROWSE FILTERS                               |
+---------------------------------------------------------------------+
|                                                                      |
|  GLAM Type:      [All] [Archive] [Museum] [Gallery] [Library] [DAM] |
|                                                                      |
|  Level:          [All Levels          V]                             |
|                  * Fonds (42)                                        |
|                  * Series (156)                                      |
|                  * File (892)                                        |
|                  * Item (2,341)                                      |
|                                                                      |
|  Repository:     [All Repositories    V]                             |
|                                                                      |
|  Creator:        [All Creators        V]                             |
|                                                                      |
|  Subject:        [All Subjects        V]                             |
|                                                                      |
|  Place:          [All Places          V]                             |
|                                                                      |
|  Genre:          [All Genres          V]                             |
|                                                                      |
|  Media:          [All Types           V]                             |
|                  * image (1,245)                                     |
|                  * document (523)                                    |
|                  * audio (89)                                        |
|                  * video (34)                                        |
|                                                                      |
|  [x] Has Digital Object                                              |
|                                                                      |
|  [Search]  [Clear Filters]                                           |
+---------------------------------------------------------------------+
```

### View Modes
```
+---------------------------------------------------------------------+
|                          VIEW MODES                                  |
+---------------------------------------------------------------------+
|                                                                      |
|  [Grid]     [List]     [Card]     [Gallery]     [Masonry]           |
|    |          |          |           |             |                 |
|    V          V          V           V             V                 |
|  +---+     +------+   +------+   +--------+   +---------+           |
|  |   |     | ---- |   |  []  |   |        |   |   | | | |           |
|  +---+     | ---- |   | ---- |   |  IMG   |   | +-+ | + |           |
|  +---+     +------+   | ---- |   |        |   | | +-+-+ |           |
|  |   |     | ---- |   +------+   +--------+   +---------+           |
|  +---+     +------+                                                  |
|                                                                      |
|  Best for:                                                           |
|  Grid    -> Photos, thumbnails, visual browsing                      |
|  List    -> Reference lists, bibliographies, inventories             |
|  Card    -> Search results, mixed content                            |
|  Gallery -> Artworks, hero images, detailed viewing                  |
|  Masonry -> Variable-size images, photo albums                       |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## GLAM Type Features

### Archive Mode
```
+---------------------------------------------------------------------+
|                        ARCHIVE MODE                                  |
+---------------------------------------------------------------------+
|  Standards: ISAD(G)                                                  |
|  Default Layout: Hierarchy                                           |
|  Icon: Archive box                                                   |
+---------------------------------------------------------------------+
|                                                                      |
|  Levels of Description:                                              |
|    * Repository                                                      |
|    * Fonds                                                           |
|    * Subfonds                                                        |
|    * Series                                                          |
|    * Subseries                                                       |
|    * File                                                            |
|    * Item                                                            |
|    * Piece                                                           |
|                                                                      |
|  Fields Displayed:                                                   |
|    - Reference Code / Identifier                                     |
|    - Title                                                           |
|    - Dates                                                           |
|    - Level of Description                                            |
|    - Extent and Medium                                               |
|    - Creator(s)                                                      |
|    - Scope and Content                                               |
|    - Arrangement                                                     |
|    - Access Conditions                                               |
|                                                                      |
+---------------------------------------------------------------------+
```

### Museum Mode
```
+---------------------------------------------------------------------+
|                        MUSEUM MODE                                   |
+---------------------------------------------------------------------+
|  Standards: Spectrum 5.0                                             |
|  Default Layout: Detail                                              |
|  Icon: Landmark                                                      |
+---------------------------------------------------------------------+
|                                                                      |
|  Levels:                                                             |
|    * Holding                                                         |
|    * Object Group                                                    |
|    * Object                                                          |
|    * Component                                                       |
|    * Specimen                                                        |
|                                                                      |
|  Fields Displayed:                                                   |
|    - Object Number                                                   |
|    - Object Name                                                     |
|    - Classification                                                  |
|    - Materials                                                       |
|    - Dimensions                                                      |
|    - Technique                                                       |
|    - Description                                                     |
|    - Condition                                                       |
|    - Production Information                                          |
|    - Provenance                                                      |
|                                                                      |
|  Available Actions:                                                  |
|    [Condition Report] [Movement] [Loan Request] [Print]             |
|                                                                      |
+---------------------------------------------------------------------+
```

### Gallery Mode
```
+---------------------------------------------------------------------+
|                        GALLERY MODE                                  |
+---------------------------------------------------------------------+
|  Standards: VRA Core                                                 |
|  Default Layout: Gallery (Hero Image)                                |
|  Icon: Palette                                                       |
+---------------------------------------------------------------------+
|                                                                      |
|  Levels:                                                             |
|    * Artist Archive                                                  |
|    * Artwork Series                                                  |
|    * Artwork                                                         |
|    * Study                                                           |
|    * Edition                                                         |
|    * Impression                                                      |
|                                                                      |
|  Fields Displayed:                                                   |
|    - Artist                                                          |
|    - Title                                                           |
|    - Date                                                            |
|    - Medium                                                          |
|    - Dimensions                                                      |
|    - Edition Information                                             |
|    - Artist Statement                                                |
|    - Exhibition History                                              |
|    - Bibliography                                                    |
|                                                                      |
|  Available Actions:                                                  |
|    [Zoom] [Add to Exhibition] [License] [Print]                     |
|                                                                      |
+---------------------------------------------------------------------+
```

### Library Mode
```
+---------------------------------------------------------------------+
|                        LIBRARY MODE                                  |
+---------------------------------------------------------------------+
|  Standards: Bibliographic (MARC-like)                                |
|  Default Layout: List                                                |
|  Icon: Book                                                          |
+---------------------------------------------------------------------+
|                                                                      |
|  Levels:                                                             |
|    * Book Collection                                                 |
|    * Book                                                            |
|    * Periodical                                                      |
|    * Volume                                                          |
|    * Issue                                                           |
|    * Chapter                                                         |
|    * Pamphlet                                                        |
|    * Map                                                             |
|                                                                      |
|  Fields Displayed:                                                   |
|    - Call Number                                                     |
|    - Title                                                           |
|    - Author                                                          |
|    - Publisher                                                       |
|    - Date                                                            |
|    - ISBN                                                            |
|    - Edition                                                         |
|    - Abstract                                                        |
|    - Table of Contents                                               |
|                                                                      |
|  Available Actions:                                                  |
|    [Request] [Cite] [Print]                                         |
|                                                                      |
+---------------------------------------------------------------------+
```

### DAM (Digital Asset Management) Mode
```
+---------------------------------------------------------------------+
|                          DAM MODE                                    |
+---------------------------------------------------------------------+
|  Focus: Photographs and Digital Assets                               |
|  Default Layout: Grid                                                |
|  Icon: Images                                                        |
+---------------------------------------------------------------------+
|                                                                      |
|  Levels:                                                             |
|    * Photo Collection                                                |
|    * Album                                                           |
|    * Shoot                                                           |
|    * Photograph                                                      |
|    * Negative                                                        |
|    * Slide                                                           |
|    * Digital Asset                                                   |
|                                                                      |
|  Fields Displayed:                                                   |
|    - Asset ID                                                        |
|    - Title                                                           |
|    - Photographer                                                    |
|    - Date Taken                                                      |
|    - Location                                                        |
|    - Caption                                                         |
|    - Keywords                                                        |
|    - Rights                                                          |
|    - Usage Terms                                                     |
|                                                                      |
|  Available Actions:                                                  |
|    [Zoom] [Download] [Add to Lightbox] [License] [Derivatives]      |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Advanced Search

### Search Fields
```
+---------------------------------------------------------------------+
|                      ADVANCED SEARCH                                 |
+---------------------------------------------------------------------+
|                                                                      |
|  General Query:    [_______________________]  [ ] Semantic Search   |
|                                                                      |
|  Title:            [_______________________]                         |
|                                                                      |
|  Identifier:       [_______________________]                         |
|                                                                      |
|  Scope/Content:    [_______________________]                         |
|                                                                      |
|  Creator (text):   [_______________________]                         |
|                                                                      |
|  Subject (text):   [_______________________]                         |
|                                                                      |
|  Place (text):     [_______________________]                         |
|                                                                      |
|                              [Search]                                |
|                                                                      |
+---------------------------------------------------------------------+
```

### Semantic Search
When enabled, semantic search expands your query with synonyms from the thesaurus:
```
  Search: "photograph"
      |
      V (Semantic Expansion)
      |
  Also searches for:
    - photo
    - image
    - picture
    - snapshot
```

---

## Sorting Options
```
+---------------------------------------------------------------------+
|                        SORT OPTIONS                                  |
+---------------------------------------------------------------------+
|                                                                      |
|  Sort by:  [Title V]    Direction: [Ascending V]                     |
|                                                                      |
|  Options:                                                            |
|    * Title (A-Z, Z-A)                                               |
|    * Identifier/Reference Code                                       |
|    * Date Added                                                      |
|    * Start Date (earliest event)                                     |
|    * End Date (latest event)                                         |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Display Preferences

### User Preferences
Users can customize their view settings (if allowed by administrator):
```
+---------------------------------------------------------------------+
|                    USER PREFERENCES                                  |
+---------------------------------------------------------------------+
|                                                                      |
|  Default View Mode:    [List      V]                                |
|                                                                      |
|  Items Per Page:       [30        V]   (10-100)                     |
|                                                                      |
|  Show Thumbnails:      [x] Yes                                       |
|                                                                      |
|  Show Descriptions:    [x] Yes                                       |
|                                                                      |
|  Card Size:            [Medium    V]                                |
|                        (Small / Medium / Large)                      |
|                                                                      |
|  [Save Preferences]   [Reset to Default]                             |
|                                                                      |
+---------------------------------------------------------------------+
```

### Preference Hierarchy
```
  User Preference (if set and allowed)
         |
         V (fallback)
  Global Setting (admin configured)
         |
         V (fallback)
  Hardcoded Default
```

---

## Exporting Data

### Print View
```
  Browse Results
       |
       V
  [Print] Button
       |
       V
  Clean printable list (up to 500 items)
```

### CSV Export
```
  Browse Results
       |
       V
  [Export CSV] Button
       |
       V
  Downloads: glam_export_YYYY-MM-DD_HHMMSS.csv

  Columns:
    - ID
    - Identifier
    - Title
    - Level
    - GLAM Type
    - Repository
    - Scope and Content
    - Extent
```

---

## Changing Object Types

### Single Object
```
  On Record Page
       |
       V
  [Change Type] Dropdown
       |
       +---> Archive
       +---> Museum
       +---> Gallery
       +---> Library
       +---> DAM
       +---> Universal
```

### Bulk Assignment
```
  Admin > Display > Bulk Set Type
       |
       V
  +-------------------------------------------+
  |  Select Collection:  [Collection A    V]  |
  |  Set Type:           [Museum          V]  |
  |  [x] Apply to all children                |
  |                                           |
  |           [Apply Changes]                 |
  +-------------------------------------------+
```

---

## Tips for Best Results

### DO:
```
+---------------------------------------------------------------------+
|  + Use filters to narrow results                                     |
|  + Select appropriate GLAM type for your collections                 |
|  + Use semantic search for broader discovery                         |
|  + Export filtered results for reports                               |
|  + Set type at collection level, then apply to children              |
+---------------------------------------------------------------------+
```

### DON'T:
```
+---------------------------------------------------------------------+
|  - Don't mix incompatible GLAM types in same hierarchy               |
|  - Don't ignore auto-detected types without reason                   |
|  - Don't forget to reindex after bulk type changes                   |
+---------------------------------------------------------------------+
```

---

## Keyboard Shortcuts
```
+---------------------------------------------------------------------+
|                     KEYBOARD SHORTCUTS                               |
+---------------------------------------------------------------------+
|                                                                      |
|  G then L    ->   Switch to List view                               |
|  G then G    ->   Switch to Grid view                               |
|  G then C    ->   Switch to Card view                               |
|  /          ->   Focus search box                                    |
|  ESC        ->   Clear filters                                       |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Need Help?

- For technical issues, contact your system administrator
- For feature requests, contact The Archive and Heritage Group

---

*Part of the AtoM AHG Framework - GLAM Browse Interface*
