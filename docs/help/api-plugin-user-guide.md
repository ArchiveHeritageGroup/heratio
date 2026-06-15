> Heratio Help Center article. Category: Plugin Reference.

# API Plugin - User Guide

## A Quick Way to Find Records by Title, Identifier or Content

The API Plugin gives administrators a fast, focused search screen for locating
archival descriptions. It searches three fields at once - title, reference
identifier, and scope and content - and returns a paginated list with direct
view and edit links for each match.

This is a lightweight lookup tool. It is not the public discovery search and it
does not change any data; it only reads from the archival description catalogue.

---

## Overview

The API Plugin exposes a single admin search screen. You type a search term, and
the plugin queries the archival description records for any match in the title,
the identifier (reference code), or the scope and content note. Results are
returned 25 at a time, ordered by title, with paging controls.

Each result row shows the record's internal ID, its identifier, and its title.
Where a record has a public page, the title links to it, and an edit link is
always offered so you can jump straight into the record.

The search excludes the catalogue root node, so you only ever see real
descriptive records.

---

## Key features

- Single search box that matches across title, identifier, and scope and content.
- Paginated results (25 per page) with previous, next, and numbered page links.
- View link (to the public record page) and edit link on every row.
- Admin-only - the screen requires you to be logged in.
- Read-only - searching never alters the catalogue.

---

## How to use

1. Log in with an administrator account.
2. Go to the search screen at **`/admin/api-plugin/search-information-objects`**.
3. Type your search term in the box. The cursor lands in the box automatically,
   so you can start typing straight away.
4. Submit the search. The screen shows the number of results found.
5. Review the results table:
   - **ID** - the record's internal number.
   - **Identifier** - the reference code.
   - **Title** - links to the public record page where one exists.
   - **Actions** - a view link and an edit link for each record.
6. Use the **Prev / Next** buttons or the numbered page links at the foot of the
   table to move through large result sets. The page list shows the current page
   plus a few pages on either side.

---

## Configuration

The API Plugin has no settings to configure. Its behaviour is fixed:

- Results are drawn from the English-culture catalogue records.
- Results are returned 25 per page.
- The catalogue root node is always excluded from results.

There is nothing to enable or tune; the search screen is available to any
logged-in administrator as soon as the package is installed.

---

## References

- Source package: `packages/ahg-api-plugin/`
- GitHub issue: [GH #546](https://github.com/ArchiveHeritageGroup/heratio/issues/546)
