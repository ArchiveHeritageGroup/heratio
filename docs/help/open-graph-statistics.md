> Heratio Help Center article. Category: Technical / Integration.

# Open Graph Statistics

## Overview

Open Graph Statistics is the "graph at a glance" surface at **/data/stats**. It publishes a live, high-level summary of the size and shape of the published open-data graph: how many records are published, the people and organisations, the subjects, places and genres, the connections between records, how well the records are described, and how many repositories hold them. Open **/data/stats** in a browser for the dashboard, or fetch **/data/stats.json** for the machine-readable figures.

---

## What it does

This feature describes the published open graph as a small set of headline numbers:

- **Published records**, broken down by level of description (collection, series, file, item, and so on), shown as simple bars.
- **People and organisations**, split into persons, corporate bodies and families.
- **Subjects, places and genres** from the controlled vocabularies.
- **Connections**: the total number of relation edges plus the record-to-record cross-links that join collections together, and how many records carry a stable linked-data URI.
- **Descriptive coverage**: the share of published records that have dates, a creator, a subject, and a linked-data URI.
- **Holding repositories**: how many distinct repositories the published records come from.

Every figure is an aggregate count over published records only. Nothing in draft, embargoed or restricted is ever counted in a way that discloses it.

---

## How to use it

1. **View the dashboard:** open **/data/stats** in a browser (for example `https://your-site.example/data/stats`) for big numbers and plain bar charts - no plugins needed.
2. **Get the data:** fetch **/data/stats.json** for the same figures as JSON, ready for a script, spreadsheet or dashboard of your own.
3. **Get a standards view:** request **/data/stats** with `Accept: application/ld+json` to receive a VoID-aligned JSON-LD dataset description (`void:entities`, `void:triples`, `void:classPartition`), suitable for open-data and linked-data tooling.
4. **Explore from there:** follow the links on the dashboard to the **graph explorer** (`/graph-explorer`), the **data catalogue** (`/data/catalog`), the **Open Memory Protocol** (`/open-data/protocol`) and the **VoID description** (`/.well-known/void`).

---

## Good to know

- The figures cover **published records only** and refresh on every request, so the page always reflects the current open collection.
- The **triple count is an estimate** - an order-of-magnitude figure for the VoID dataset description, not an exact statement count. Every other number is an exact count.
- The endpoints are **open data** under CC-BY-4.0 and are CORS-open, so any site or tool may fetch them.
- An **empty collection** shows a clear empty-state message rather than an error; the surface never fails.
- Use **/data/stats** for the at-a-glance picture; for the full machine catalogue of open surfaces use **/open-data/protocol** or **/data/catalog**, and for bulk record data use the dataset dumps at **/api/v1/dataset.csv** and **/api/v1/dataset.jsonld**.
