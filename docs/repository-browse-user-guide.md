# Archival Institution Browse

## User Guide

Browse and search archival institutions (repositories) with faceted filtering, multiple view modes, and advanced search options.

---

## Overview
```
+---------------------------------------------------------------------+
|                  ARCHIVAL INSTITUTION BROWSE                          |
+---------------------------------------------------------------------+
|                                                                      |
|   /repository/browse                                                |
|       |                                                              |
|       V                                                              |
|   +-----------------------------------------------------------+     |
|   | Search + Facets + Advanced Filters + View Modes            |     |
|   +-----------------------------------------------------------+     |
|       |                                                              |
|       +---> Card View (logos/names in grid)                          |
|       +---> Table View (name, region, locality, thematic area)      |
|       +---> Tree View / History (via DisplayModeService)            |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## How to Access

```
  Main Menu
      |
      V
   Browse
      |
      +---> Archival Institution
            (/repository/browse)
```

---

## Page Layout
```
+---------------------------------------------------------------------+
|                    REPOSITORY BROWSE                                  |
+---------------------------------------------------------------------+
|                                                                      |
|  +--SIDEBAR--+  +--MAIN CONTENT-----------------------------------+ |
|  |           |  |                                                   | |
|  | Facets    |  |  [University Icon] Showing 11 results            | |
|  |           |  |                    Archival institution           | |
|  | Language  |  |                                                   | |
|  | Archive   |  |  [Search institutions...]                        | |
|  |  type     |  |                                                   | |
|  | Region    |  |  [Advanced search options    V]                  | |
|  | Subregion |  |  | Thematic area | Type | Region |              | |
|  | Locality  |  |  | [Set filters]                |              | |
|  | Thematic  |  |                                                   | |
|  |  area     |  |  [Grid] [List] [Tree] [History]                  | |
|  |           |  |           [Date modified V] [Name] [Identifier]  | |
|  +-----------+  |                                                   | |
|                 |  +--RESULTS--------------------------------------+ |
|                 |  | Card grid / Table depending on view mode      | |
|                 |  +----------------------------------------------+ | |
|                 |                                                   | |
|                 |  [< 1 2 3 >]                                     | |
|                 +---------------------------------------------------+ |
|                                                                      |
+---------------------------------------------------------------------+
```

---

## Facet Filters

The sidebar provides six facet categories for narrowing results:

| Facet | Description |
|-------|-------------|
| Language | Filter by language of the repository record |
| Archive type | Type of institution (e.g., National, University, Corporate) |
| Geographic Region | Province or state |
| Geographic Subregion | Subregion within the region |
| Locality | City or town |
| Thematic Area | Subject specialty of the institution |

---

## View Modes

```
  View Modes:
      |
      +---> Grid/Card     Logos and names in card layout
      +---> List/Table     Sortable table (Name, Region, Locality, Thematic Area)
      +---> Tree/Sitemap   Hierarchical view
      +---> History        Recent updates view
```

### Card View
- Repository logos displayed as card images
- Repository name below each logo
- Clipboard button for each repository
- Responsive 3-column grid (2 on tablet, 1 on mobile)

### Table View
- Sortable columns: Name, Region, Locality
- Click column headers to sort ascending/descending
- Thematic area displayed as list items
- Clipboard button column

---

## Search

### Inline Search
Type directly in the search bar to filter repositories by name or content.

### Advanced Filters
Expand the "Advanced search options" panel to filter by:

```
  Advanced Filters:
      |
      +---> Thematic Area    (dropdown of all thematic areas)
      +---> Archive Type      (dropdown of all repository types)
      +---> Region            (dropdown of unique regions)
      |
      [Set filters]
```

---

## Sorting

| Sort Option | Description |
|-------------|-------------|
| Date modified | Most recently updated first (default) |
| Name | Alphabetical by institution name |
| Identifier | By repository identifier |

In table view, additional column-level sorting:
- Click "Name" header to sort by name (asc/desc toggle)
- Click "Region" header to sort by region
- Click "Locality" header to sort by locality

---

## Tips

- **Use card view** for a visual overview with logos
- **Use table view** when you need to see region and locality at a glance
- **Combine facets** to narrow results (e.g., Language + Region)
- **Advanced filters** are useful for finding repositories by thematic specialty
