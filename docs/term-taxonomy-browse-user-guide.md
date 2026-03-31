# Term & Taxonomy Browse

## User Guide

Browse terms (subjects, places, genres) and taxonomy listings with high-performance search, sorting, and count columns.

---

## Overview
```
+---------------------------------------------------------------------+
|                     TERM & TAXONOMY BROWSE                           |
+---------------------------------------------------------------------+
|                                                                      |
|   TAXONOMY BROWSE              TERM BROWSE                          |
|   /taxonomy/:id                /term/:slug                          |
|       |                            |                                |
|       V                            V                                |
|   +-------------------+       +-------------------+                 |
|   | List all terms    |       | View single term  |                 |
|   | in a taxonomy     |       | + related records |                 |
|   +-------------------+       +-------------------+                 |
|       |                            |                                |
|       V                            V                                |
|   Subjects (35)               Photography                           |
|   Places (42)                 South Africa                          |
|   Genres (78)                 Watercolour                           |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## What Does This Plugin Do?

This plugin replaces base AtoM's taxonomy and term browse pages with optimised versions that load significantly faster, especially for large collections.

```
+---------------------------------------------------------------------+
|                    PERFORMANCE COMPARISON                             |
+---------------------------------------------------------------------+
|                                                                      |
|   BASE AtoM                      THIS PLUGIN                       |
|       |                              |                              |
|       V                              V                              |
|   For each row:                  Single batch query:                |
|     Query 1: IO count             1 query for ALL IO counts        |
|     Query 2: Actor count          1 query for ALL actor counts     |
|     ...x30 rows = 60 queries     = 2 queries total                 |
|                                                                      |
|   Result: Slow page loads         Result: Sub-second loads          |
|   (2-10+ seconds)                (< 0.5 seconds)                   |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## How to Access

### Taxonomy Browse (List of Terms)

Navigate via the main menu:

```
  Main Menu
      |
      V
   Browse
      |
      +---> Subjects    (/taxonomy/35)
      |
      +---> Places      (/taxonomy/42)
      |
      +---> Genres      (/taxonomy/78)
```

### Term Browse (Individual Term Page)

Click any term name from a taxonomy listing, or navigate directly:

```
  Taxonomy Browse
      |
      V
   Click term name
      |
      V
   Term Page (/term/:slug)
      |
      +---> Term details (fields, scope notes)
      +---> Related information objects
      +---> Sidebar with facets
      +---> Treeview navigation
```

---

## Taxonomy Browse

### Page Layout
```
+---------------------------------------------------------------------+
|                       TAXONOMY BROWSE                                |
+---------------------------------------------------------------------+
|                                                                      |
|  +--SIDEBAR--+  +--MAIN CONTENT-----------------------------------+ |
|  |           |  |                                                   | |
|  | Treeview  |  |  [Icon] Showing 75 results                      | |
|  | of terms  |  |         Subjects                                 | |
|  |           |  |                                                   | |
|  | - Term A  |  |  [Search subjects...] [All labels V]            | |
|  | - Term B  |  |                        [Date modified V] [Name] | |
|  | - Term C  |  |                                                   | |
|  | - Term D  |  |  +--TABLE--------------------------------------+ | |
|  | - Term E  |  |  | Term        | Scope note | IO # | Actor #  | | |
|  |   ...     |  |  |-------------|------------|------|----------| | |
|  |           |  |  | Photography | ...        |   12 |        0 | | |
|  | [< Prev]  |  |  | Portraits  | ...        |    5 |        2 | | |
|  | [Next >]  |  |  | Landscapes | ...        |    8 |        1 | | |
|  |           |  |  |   ...       |            |      |          | | |
|  +-----------+  |  +--------------------------------------------+ | |
|                 |                                                   | |
|                 |  [< 1 2 3 >]              [Add new]              | |
|                 +---------------------------------------------------+ |
|                                                                      |
+---------------------------------------------------------------------+
```

### Table Columns

| Column | Description |
|--------|-------------|
| Term name | Clickable link to individual term page. Shows descendant count in parentheses and "Use for" labels beneath |
| Scope note | Scope notes attached to the term |
| IO count | Number of information objects related to this term (batch-counted) |
| Actor count | Number of authority records related to this term (batch-counted) |

### Search Within Taxonomy

Use the inline search bar to filter terms:

```
  Search Fields:
      |
      +---> All labels         (preferred + use-for names, default)
      +---> Preferred label    (official term name only)
      +---> "Use for" labels   (alternate names only)
```

### Sorting

```
  Sort Options:
      |
      +---> Date modified   (most recently updated first, default)
      +---> Name            (alphabetical A-Z)
```

### Per-Taxonomy Icons

| Taxonomy | Icon | URL |
|----------|------|-----|
| Places | Map marker | /taxonomy/42 |
| Subjects | Tag | /taxonomy/35 |
| Genres | (none) | /taxonomy/78 |

---

## Term Browse

### Page Layout
```
+---------------------------------------------------------------------+
|                          TERM BROWSE                                 |
+---------------------------------------------------------------------+
|                                                                      |
|  +--SIDEBAR--+  +--MAIN--+  +--CONTEXT MENU--+                     |
|  |           |  |        |  |                 |                     |
|  | Treeview  |  | Term   |  | Navigation      |                     |
|  | of terms  |  | Title  |  | Related counts  |                     |
|  |           |  |        |  |                 |                     |
|  | Facets:   |  | Fields |  +-----------------+                     |
|  | - Language|  | area   |                                          |
|  | - Places  |  |        |                                          |
|  | - Subjects|  | Actions|                                          |
|  | - Genres  |  |        |                                          |
|  |           |  | Browse |                                          |
|  |           |  | results|                                          |
|  |           |  | (IOs)  |                                          |
|  |           |  |        |                                          |
|  +-----------+  +--------+                                          |
|                                                                      |
+---------------------------------------------------------------------+
```

### Features

- **Term details**: Fields area with scope notes, source notes, relationships
- **Breadcrumb**: Shows term hierarchy path
- **Browse results**: Information objects related to this term
- **Direct filter**: Toggle "Only results directly related" to exclude inherited relationships
- **Faceted search**: Filter by language, places, subjects, genres
- **Sort options**: Date modified, Title, Reference code, Start date

---

## Access Control

```
+---------------------------------------------------------------------+
|                        ACCESS CONTROL                                |
+---------------------------------------------------------------------+
|                                                                      |
|   Taxonomy              Anonymous       Editor/Admin                |
|       |                     |               |                       |
|       V                     V               V                       |
|   Places (42)           Allowed          Allowed                    |
|   Subjects (35)         Allowed          Allowed                    |
|   Genres (78)           Allowed          Allowed                    |
|   All other             403 Forbidden    Allowed                    |
|   Locked                403 Forbidden    403 Forbidden              |
|                                                                      |
|   "Add new" button:     Hidden           Visible (if createTerm)   |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Tips

- **Use the treeview sidebar** to navigate between terms in the same taxonomy without returning to the listing
- **Search by "Use for" labels** to find terms even when you know them by an alternate name
- **Sort alphabetically** when looking for a specific term; sort by date modified to see recently updated terms first
- **IO and Actor counts** reflect the total number of directly related records (not inherited through the hierarchy)
