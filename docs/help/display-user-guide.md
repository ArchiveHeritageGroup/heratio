> Heratio Help Center article. Category: Discovery / Browse.

# GLAM Browse and Discovery

The GLAM Browse page at `/glam/browse` is the single, unified discovery surface for every record in Heratio. It lets visitors and staff page through archival descriptions, switch between five display layouts, narrow results with a column of facets, run keyword and field-level searches, and drill into a collection and all of its descendants using the hierarchy filter.

---

## Overview

Heratio ships one browse page, served by the `ahg-display` package. All other browse entry points funnel into it:

- `/glam/browse` is the canonical URL.
- `/informationobject/browse` redirects here, translating its parameters (`collection` becomes `ancestor`, `topLod` becomes `topLevel`, `onlyMedia` becomes `hasDigital`).
- Sector show pages and embedded widgets reuse the same controller through `/glam/browseAjax`.

The page shows a left-hand facet sidebar, a results toolbar (view switcher, sort, page size, export, print), and the result list itself. Anonymous visitors see only published records; signed-in staff see everything, including unpublished drafts and a diagnostics panel for records with broken links.

The browse surface is jurisdiction-neutral and works the same for any market. GLAM sector types (archive, museum, gallery, library, photo/DAM) are configurable per record, not baked into the core.

---

## Key features

- **Five display layouts**: card, grid, table, full-width, and marketplace-style tile. The active layout is carried in the `view` URL parameter.
- **Faceted sidebar**: GLAM type, level of description, repository, creator, subject, place, genre, and media type. Library-only facets (material type, condition grade, acquisition method, circulation status) appear automatically when the result set includes library items.
- **Keyword search** across titles and descriptions, with an optional semantic-expansion mode that pulls in related terms from the thesaurus.
- **Advanced field search**: title, identifier, reference code, scope and content, extent and medium, archival history, acquisition, creator, subject, place, genre, and a start/end date range with inclusive or exact matching.
- **Hierarchy (ancestor) filter**: selecting a collection shows that record plus every descendant, using the nested-set `lft`/`rgt` range rather than direct children only.
- **Sorting** by title, identifier or reference code, date modified, and event start or end date, ascending or descending.
- **Page size control**: 10, 30, 50, or 100 results per page.
- **CSV export** (up to 5,000 rows) and a **print view** (up to 500 rows) of the current filtered result set.
- **Work-set clustering**: multiple editions of the same library work collapse into a single row with a "view all editions" expander.
- **Per-user browse preferences** for signed-in users (default layout, sort, page size, facet visibility, filter memory).

---

## How to use

### Open the browse page

1. Go to `/glam/browse` (or follow any "Browse" link in the main navigation).
2. The page opens on the default layout and sort. Operators can pin a default sector so a museum-only or library-only site lands on the right records first.

### Switch the display layout

Use the view buttons in the results toolbar. Each one reloads the page with a different `view` value:

| Button | `view` value | Best for |
|---|---|---|
| Card | `card` | Default; thumbnail plus summary |
| Grid | `grid` | Dense image-led browsing |
| Table | `table` | Scannable rows with resizable columns |
| Full width | `full` | One record per row with a large preview; hides the sidebar |
| Tile | `tile` | Marketplace-style image tiles |

### Narrow results with facets

The left sidebar lists each facet with a count badge. Click any value to add it as a filter; the URL updates (for example `?type=museum` or `?subject=42`) and the counts recompute against the active filters. Click "All" at the top of a facet to clear it. Combine facets freely; they are ANDed together.

### Search by keyword

1. Type your terms into the search box and submit.
2. Results are filtered by the `query` parameter against titles and descriptions.
3. To broaden the search to related terms, enable semantic mode (`semantic=1`); the thesaurus expands your query and matches any of the related terms.

A bare wildcard such as `*` is ignored rather than treated as a search.

### Use advanced field search

Open the advanced search panel to target individual fields. Each maps to a URL parameter:

- `title`, `identifier`, `referenceCode`
- `scopeAndContent`, `extentAndMedium`, `archivalHistory`, `acquisition`
- `creatorSearch`, `subjectSearch`, `placeSearch`, `genreSearch`
- `startDate` and `endDate` with `rangeType` set to `inclusive` (overlapping) or `exact`

### Browse a collection hierarchy

1. From a result, follow the collection or open it via `?ancestor=<id>` (legacy `collection=<id>` also works).
2. The page shows the chosen record and all of its descendants, with a breadcrumb trail back to the top level.
3. To list only top-level entry points, use `topLevel=1`.

### Sort and page

- Use the sort dropdown to pick a field and direction. Options: date modified, title, identifier/reference code, event start date, event end date.
- Use the page-size dropdown to show 10, 30, 50, or 100 per page.

### Export or print

- **CSV**: the CSV button streams the current filtered set (ID, identifier, title, level, GLAM type, repository, scope and content, extent), capped at 5,000 rows, with a UTF-8 byte-order mark for spreadsheet compatibility.
- **Print**: the print view renders a printable list of the current filters, capped at 500 rows.

### Save your browse preferences (signed-in users)

Go to the browse settings page (`/glam/settings`) to set your personal defaults: preferred layout, default sort field and direction, items per page (10 to 100), whether to show facets, and whether to remember your last filters. These can be reset to defaults at any time.

---

## Configuration

These are administrator tasks. The admin routes under `/glam/*` require the `admin` middleware.

- **Enable or disable GLAM browse** (`enable_glam_browse`): a master switch under AHG Settings themes. When off, `/glam/browse` returns 404 and sector show pages become the only browse surfaces.
- **Default sector** (`default_sector`): the GLAM type a visitor lands on when no narrowing parameter is supplied. Leave empty for no default.
- **Default browse sort** (`sort_browser_anonymous`, `sort_browser_user`, plus direction variants): separate defaults for anonymous and signed-in users, used when no `sort` is in the request.
- **Single vs multi-repository** (`multi_repository`, `single_repo_id`): when multi-repository is off, the whole browse surface is locked to one repository and the repository facet is hidden.
- **GLAM type per record**: set a record's type (archive, museum, gallery, library, dam, universal) at `/glam/setType` or `/glam/changeType`, optionally cascading to all descendants. Bulk type assignment for whole collections is at `/glam/bulkSetType`.
- **Display profiles, levels, and fields**: manage which fields show for each profile at `/glam/profiles`, `/glam/fields`, and `/glam/levels`; assign a profile to a record at `/glam/assignProfile`.
- **Facet performance**: facet counts come from a cache table for unfiltered browsing and are computed live once filters are active. The optional denormalised sidecar (`ahg_display_use_facet_denorm`, off by default) speeds up subject/place/genre facets; populate it with the `PopulateIoFacetDenormCommand` console command before turning the flag on.

GLAM type labels and any enumerated values follow the central Dropdown Manager; they are never hardcoded.

---

## References

- Source: `packages/ahg-display/`
- Issue: [GH #561](https://github.com/ArchiveHeritageGroup/heratio/issues/561)
- Main routes: `/glam/browse`, `/glam/browseAjax`, `/glam/print`, `/glam/exportCsv`
- Admin routes: `/glam`, `/glam/profiles`, `/glam/levels`, `/glam/fields`, `/glam/setType`, `/glam/changeType`, `/glam/bulkSetType`, `/glam/assignProfile`, `/glam/settings`, `/glam/treeview`
- Controller: `packages/ahg-display/src/Controllers/DisplayController.php`
- Service: `packages/ahg-display/src/Services/DisplayService.php`
- Browse view: `packages/ahg-display/resources/views/display/browse.blade.php`
