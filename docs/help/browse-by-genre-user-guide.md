> Heratio Help Center article. Category: Discovery & Browse.

# Browse by Genre User Guide

## Overview

Browse by Genre organises the published holdings by the genres and forms they carry. It surfaces the genre and document-form access points already attached to your published records - the genres under which the most records sit - as "ways into the collection", so you can start from a genre rather than from a search box. Genres are shown as a frequency-sized cloud and a ranked list; each one shows how many published records carry it and links straight to those records. Open it at **/genres**. It completes the taxonomy-browse set alongside **Explore by Theme** (which groups by subject) and **Browse by Place** (which groups by geography).

---

## What it does

Browse by Genre reads the genre access points already attached to your published records and ranks them by how many records carry each one:

- The busiest genres become the largest chips in the cloud - the collection's genre/form "ways in".
- Each genre shows its published-record count.
- Opening a genre lists the published records of it, with links to each record and a one-click route into the full browse for that genre.

Only published records are counted and shown. Genres update automatically as records are described with genre access points and made public - there is nothing to configure or generate. No vocabulary is built in: the genre names come entirely from your own catalogue.

---

## How to use it

1. Go to **/genres**.
2. Browse the cloud - larger names carry more published records - or use the ranked **All genres by frequency** list below it.
3. Click a genre name to open it.
4. On a genre page you will see:
   - The genre's label and, where the catalogue holds one, a short scope note.
   - The total number of published records of the genre.
   - A paginated list of those records, each linking to the record in full.
   - A **Browse all of this genre** button that opens the main browse page pre-filtered to this genre, where you can apply further filters (creator, subject, place, media, level, and so on).
5. Use the page links at the bottom of a genre to move through long lists.

---

## Machine-readable genre list

A read-only JSON list of the genres is available at **/genres.json** for reuse in
other tools and integrations. It is CORS-open and cacheable, and returns each
genre's id, label, published-record count, and links. No record content or
unpublished material is exposed.

---

## Good to know

- **Published only.** Genres are built from published records, so the counts match
  what the public can actually open.
- **It stays current.** Genre rankings and record lists are computed on the fly
  from the live catalogue, so they reflect the collection as it is right now.
- **It completes the browse set.** Where **Explore by Theme** groups records by
  subject, **Browse by Place** groups them by geography, and **Discoveries** /
  **Research Leads** show AI-found links between records, Browse by Genre groups
  records by genre and form - different ways to find related material.
