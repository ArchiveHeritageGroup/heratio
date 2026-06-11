> Heratio Help Center article. Category: Technical / Integration.

# Open Data Graph API

## Overview

The Open Data Graph API exposes a single record as a linked-data graph for developers and machines. Given a record identifier, it returns that record together with its typed relationships - creators, subjects, places, related records, and other connections - in a structured, machine-readable form that other systems can consume directly. It is the building block for integrations, aggregators, research tools, and linked-open-data publishing. The endpoint is **GET /api/v1/graph/{id}**.

---

## What it does

The endpoint returns a graph-shaped, linked-data view of one record:

- **Subject:** the record identified by `{id}` becomes the central node of the graph.
- **Edges:** the record's relationships are expressed as typed links to other entities - for example its creators, subjects, places, repository, and related records.
- **Machine-readable:** the response is structured data designed for programmatic use, so a client can follow the edges to traverse the collection as a graph rather than scraping HTML pages.

This makes it straightforward to pull a record and its context into another application, to feed an aggregator, or to publish records as linked open data.

---

## How to use it

1. Identify the record you want by its identifier (`{id}`).
2. Make an HTTP `GET` request to **/api/v1/graph/{id}** on the platform's API host.
3. Parse the returned graph - read the subject node and follow the typed edges to the related entities.
4. To traverse further, request the graph for an entity referenced by one of the edges, using its identifier in turn.

### Example

```bash
# Fetch the linked-data graph for record 12345
curl -s "https://your-site.example/api/v1/graph/12345"
```

A response describes the record as a node and its relationships as typed edges, for example (shape illustrative):

```json
{
  "id": "12345",
  "type": "InformationObject",
  "title": "Harbour Construction Photographs",
  "edges": [
    { "predicate": "creator",   "target": "67890", "label": "Public Works Department" },
    { "predicate": "subject",   "target": "11223", "label": "Harbours" },
    { "predicate": "place",     "target": "44556", "label": "Port District" },
    { "predicate": "isPartOf",  "target": "10000", "label": "Public Works Fonds" }
  ]
}
```

Follow any `target` to retrieve that entity's own graph from the same endpoint.

---

## Good to know

- **Scope:** the endpoint returns one record's node plus its immediate typed relationships. Traverse the graph by requesting the connected identifiers, rather than expecting the whole collection in a single call.
- **Access rules apply:** only records that are publicly accessible are returned. Restricted or unpublished records are not exposed through this endpoint.
- **Stable identifiers:** use the returned identifiers when storing or linking, as they are the durable way to reference an entity across requests.
- **Read-only:** this is a `GET` endpoint for reading the graph; it does not create or modify records.
- For broader API capabilities beyond the graph view, see the platform's main API technical reference.
