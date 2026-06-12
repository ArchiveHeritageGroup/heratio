> Heratio Help Center article. Category: Discovery & Browse.

# Browse by Place User Guide

## Overview

Browse by Place organises the published holdings by the places they are about. It surfaces the geographic access points already attached to your published records - the places under which the most records sit - as "ways into the collection", so you can start from a place rather than from a search box. Places are shown as a frequency-sized cloud and a ranked list; each one shows how many published records are about it and links straight to those records. Open it at **/places**. It is the geography companion to **Explore by Theme** (which groups by subject).

---

## What it does

Browse by Place reads the place access points already attached to your published records and ranks them by how many records are about each one:

- The busiest places become the largest chips in the cloud - the collection's geographic "ways in".
- Each place shows its published-record count.
- Opening a place lists the published records about it, with links to each record and a one-click route into the full browse for that place.

Only published records are counted and shown. Places update automatically as records are described with geographic access points and made public - there is nothing to configure or generate. No geography is built in: the place names come entirely from your own catalogue.

---

## How to use it

1. Go to **/places**.
2. Browse the cloud - larger names are about more published records - or use the ranked **All places by frequency** list below it.
3. Click a place name to open it.
4. On a place page you will see:
   - The place's label and, where the catalogue holds one, a short scope note.
   - The total number of published records about the place.
   - A paginated list of those records, each linking to the record in full.
   - A **Browse all about this place** button that opens the main browse page pre-filtered to this place, where you can apply further filters (creator, subject, media, level, and so on).
5. Use the page links at the bottom of a place to move through long lists.

---

## Machine-readable place list

A read-only JSON list of the places is available at **/places.json** for reuse in
other tools and integrations. It is CORS-open and cacheable, and returns each
place's id, label, published-record count, and links. No record content or
unpublished material is exposed.

---

## Good to know

- **Published only.** Places are built from published records, so the counts match
  what the public can actually open.
- **It stays current.** Place rankings and record lists are computed on the fly
  from the live catalogue, so they reflect the collection as it is right now.
- **It complements the other slices.** Where **Explore by Theme** groups records by
  subject and **Discoveries** / **Research Leads** show AI-found links between
  records, Browse by Place groups records by geography - different ways to find
  related material.
