---
category: User Guide
title: Exhibition spaces — front-of-house space allocation
---

# Exhibition spaces — front-of-house space allocation

Where the **Strongrooms** module manages back-of-house storage, **Exhibition spaces** manages your front-of-house: galleries, halls, display cases, plinths, vitrines. It tracks how much physical or wall space each room has, what's currently on display, and warns you before you over-commit a space.

## What's different from Strongrooms?

| | Strongrooms | Exhibition spaces |
|---|---|---|
| Audience | Back-of-house storage (curators, registrars) | Front-of-house display (curators, exhibition designers) |
| What's placed | Physical objects (boxes, crates, archival storage units) | Information objects — artworks, artefacts, the records that have an `informationobject` page |
| Time-boundedness | Open-ended (object lives in the strongroom until moved) | **Date-bounded** (an exhibit is in a gallery from Jun 1 to Aug 31) |
| Capacity model | Boxes, shelves, linear metres, cubic metres | Linear wall metres, display cases, plinths, square metres |
| Capacity validation | Total used vs total capacity | Total used **during the requested date range** vs capacity |
| Extra metadata | Location description, notes | Building, floor, lighting lux target (optional) |

## Adding an exhibition space

1. Go to **AHG Plugins → Exhibition spaces** (or the `/exhibition-space/browse` URL).
2. Click **Add exhibition space**.
3. Fill in:
   - **Name** (required) — e.g. *"Gallery 3 – Modern Wing"*
   - **Type** — Gallery / Hall / Display case / Plinth / Vitrine
   - **Building** + **Floor** (optional but useful for large institutions)
   - **Capacity** + **Unit** — leave blank if you don't want capacity enforcement; otherwise pick a unit (linear wall metres, display cases, plinths, square metres) and enter the number
   - **Lighting target (lux)** — optional, captures the target light level for conservation purposes
   - **Notes**
4. Save. You'll land on the space's detail page where you can start adding placements.

## Placing an information object in a space

From the exhibition space's detail page:

1. Scroll to the **Add a placement** form at the bottom.
2. Enter the **Information object ID** — the numeric id of the record (e.g. `1234`). Tip: the id appears in the URL when you view an information object.
3. **Units used** — how much of the space's capacity this placement consumes. For a wall-mounted painting needing 2.5 linear metres, enter `2.5`. For a sculpture on its own plinth, enter `1` (one plinth).
4. **Starts** + **Ends** — the date range the object will be on display. Leave both blank for an open-ended placement (rare but supported).
5. **Notes** — optional remarks about the placement.
6. Click **Place**.

## Capacity is date-aware

This is the most important difference from strongrooms. Two placements only **conflict** if their date ranges overlap.

Example: Gallery 3 has a 10 linear-metre wall.

- **Placement A** — Painting X, uses 8 m, Jun 1 – Aug 31. ✓
- **Placement B** — Painting Y, uses 9 m, Sep 1 – Nov 30. ✓ (No overlap. Even though 8+9=17 exceeds 10, they're not in the gallery at the same time.)
- **Placement C** — Sculpture Z, uses 5 m, Jul 15 – Sep 30. ✗ **Rejected** — between Jul 15 and Aug 31, this would put 8+5=13 m on a 10 m wall.

When a placement is rejected for over-capacity, the system tells you the date range and the amount of overflow, so you can shrink the placement, shift its dates, or pick a different space.

## Removing a placement

From the space's detail page, each placement row has a delete button (visible to logged-in users). Removing a placement frees up the capacity immediately for that date range.

## Browsing your spaces

The browse page (`/exhibition-space/browse`) shows every space with:

- **Current utilisation** — a progress bar showing what fraction of capacity is in use **today** (not for any other date — for that, open the specific space)
- **Current placements** — how many information objects are currently on display in this space

The progress bar turns amber at 70% and red at 90% of capacity, so you can spot pressure at a glance.

## Deleting an exhibition space

You can delete an empty space (no placements). A space with placements is blocked — remove the placements first. This is a safety check, not a deletion of placement history per se; you can always delete the placements then the space.

## What this doesn't (yet) do

- **No floor-plan map** — placements are listed in a table, not pinned to a visual floor plan. (That's a future enhancement.)
- **No best-fit allocator** — the system tells you when a placement won't fit, but doesn't suggest alternatives.
- **No lighting/temp/humidity monitoring** — the `lighting_lux_target` field is captured but not actively monitored. Pair with your environmental monitoring system out-of-band.
- **No automatic placement from an exhibition** — if you have a curated exhibition with an object list, the placements still need to be entered here individually. Future work.

## Quick links

- **Browse all spaces** — `/exhibition-space/browse`
- **Add a space** — `/exhibition-space/add` (requires login)
- **Strongrooms** (sibling module for back-of-house storage) — `/strongroom/browse`
