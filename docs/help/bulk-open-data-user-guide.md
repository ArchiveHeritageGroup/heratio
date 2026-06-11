> Heratio Help Center article. Category: Technical / Integration.

# Bulk Open Data User Guide

## Overview

Bulk Open Data lets anyone download the platform's published records as complete dataset dumps, without scraping pages one at a time. Two endpoints serve the data: **/api/v1/dataset.csv** returns the published records as CSV, and **/api/v1/dataset.jsonld** returns them as JSON-LD. The JSON-LD feed is paginated with an **?after** cursor, so a client can walk through the whole dataset page by page until there is nothing left to fetch. Only published records are included. Start at **/api/v1/dataset.csv** or **/api/v1/dataset.jsonld**.

---

## What it does

This feature publishes the open portion of the catalogue as reusable bulk data:

- It exposes **/api/v1/dataset.csv**, a CSV dump of the published records suitable for spreadsheets, data tools, and quick analysis.
- It exposes **/api/v1/dataset.jsonld**, the same published records as **JSON-LD** linked data for semantic and integration use.
- It **paginates the JSON-LD feed** using an **?after cursor**, so large datasets can be retrieved in successive pages rather than in one oversized response.
- It includes **only published records**, so the dumps reflect what is already open to the public.
- It gives integrators, researchers, and aggregators a stable, machine-readable way to harvest the open dataset in full.

The aim is open, repeatable access to the published collection as data.

---

## How to use it

1. **Download the CSV:** fetch **/api/v1/dataset.csv** (for example `https://your-site.example/api/v1/dataset.csv`) and open it in a spreadsheet or load it into a data tool.
2. **Fetch the JSON-LD:** request **/api/v1/dataset.jsonld** to receive the first page of published records as linked data.
3. **Page through with the cursor:** take the **?after** value supplied with a JSON-LD page and pass it on the next request (for example `https://your-site.example/api/v1/dataset.jsonld?after=<cursor>`) to retrieve the following page.
4. **Repeat** the cursor step until a page returns no further records, at which point you have harvested the whole dataset.
5. Re-run the harvest later to pick up newly published records.

---

## Good to know

- The dumps contain published records only - anything embargoed, restricted, or in draft is excluded by design, so the open data stays open.
- Use the CSV for human-friendly, tabular work and the JSON-LD for linked-data, integration, and interoperability work; they cover the same published records in different shapes.
- Always follow the **?after** cursor on the JSON-LD feed rather than guessing page numbers - the cursor is the reliable way to traverse the full set without gaps or duplicates.
- Because the feeds reflect what is currently published, repeating a harvest is the correct way to stay in sync as the collection grows.
- Treat these endpoints as a bulk-harvest surface; for targeted lookups of individual records, use the platform's record-level API instead.
