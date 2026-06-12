> Heratio Help Center article. Category: Discovery & Browse.

# Explore the Collection User Guide

## Overview

Explore the Collection is a single public hub that gathers the collection's browse-by surfaces in one place, so you can start exploring from a theme, a place, a person, or a period rather than from a search box. It shows a small teaser from each surface and links straight through to the full page. Open it at **/explore-collection**.

It complements the existing **/explore** hub: where /explore lists the collection's public capabilities (ask the collection, read in your language, content credentials, open data, and so on), Explore the Collection focuses on the browse-by discovery surfaces specifically.

---

## What it does

Explore the Collection brings together the discovery surfaces this collection ships and previews each one:

- **Explore by theme** - a handful of the collection's strongest subjects, each linking to that theme and to the full themes page.
- **Browse by place** - the busiest places the records are about, linking to each place and to the full places page.
- **People and organisations** - the people and organisations credited with creating the most records, linking to each creator and to the full people page.
- **Browse by period** - a compact timeline strip showing how the records spread across time, linking each period into the browse and to the full timeline.

Each panel is a small sample drawn live from the published collection. A panel appears only when its surface is installed, so you never see a dead link. If none of the surfaces has anything to show yet, the hub shows a calm "exploration tools are warming up" message instead of an error.

Only published records are counted and shown.

---

## How to use it

1. Go to **/explore-collection**.
2. Skim the panels. Each one is a different door into the same holdings.
3. Click any item to open it - a theme, a place, a creator, or a period.
4. Click a panel's **Browse all** link (for example, **Browse all themes**) to open the full surface, where you can page through everything and apply further filters.
5. The timeline strip's bars link into the main browse, pre-filtered to that period.

---

## Machine-readable hub data

A read-only JSON twin of the hub is available at **/explore-collection.json** for reuse in other tools and integrations. It is CORS-open and cacheable, and returns the same per-surface teaser data (only for the surfaces that are installed). No record content or unpublished material is exposed.

---

## Good to know

- **Published only.** Every teaser is built from published records, so the counts match what the public can actually open.
- **It stays current.** The teasers are computed on the fly from the live catalogue, so they reflect the collection as it is right now.
- **No dead links.** Each panel and onward link only renders when its surface is installed.
- **It is a hub, not a new surface.** The full experiences live on their own pages (**/themes**, **/places**, **/people**, **/timeline**); this page simply gathers a taste of each.
