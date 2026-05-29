> Heratio Help Center article. Category: Settings.

# Default Browse Sort Direction - User Guide

**Version:** 1.0
**Date:** 2026-05-29
**Module:** ahg-settings / ahg-display

---

## 1. Overview

You can set the default **sort direction** (ascending or descending) for the
GLAM item browse (`/glam/browse`), independently for authenticated staff and for
anonymous (public) visitors. It complements the existing default sort *field*
setting.

## 2. Where to set it

Go to **Admin -> Settings -> Global** (`/admin/settings/global`). Two controls:

- **Sort direction (authenticated users)** - default for signed-in staff.
- **Sort direction (anonymous users)** - default for public visitors.

Each offers **Descending** (newest / Z-A first) or **Ascending** (oldest / A-Z
first). Save to apply. The default is Descending until changed.

## 3. How it behaves

- The saved direction is only the **default**. When a user clicks a column or
  direction control on the browse page, that choice (the `?dir=` / `?sortDir=`
  URL parameter) takes precedence for that view.
- The default fills in only when the request has no explicit direction - e.g.
  the first time a visitor opens `/glam/browse`.

## 4. Notes

- Settings are stored in the standard settings store; saving creates the
  underlying setting record automatically if it does not yet exist.
- This setting affects the browse list ordering, not search-relevance ranking.
