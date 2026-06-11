> Heratio Help Center article. Category: Technical / Integration.

# Open Data: Graph, Sitemap, and OAI-PMH User Guide

## Overview

The open-data endpoints let developers and aggregators consume the collection as machine-readable data. A linked-data graph is served at **/api/v1/graph** with content negotiation, so you get the serialization you ask for. A VoID description at **/.well-known/void** describes the dataset, a graph sitemap helps crawlers find the linked-data resources, and an OAI-PMH endpoint at **/api/oai** supports standards-based metadata harvesting. Together they make the catalogue discoverable and reusable by other systems. Start at **/api/v1/graph**.

---

## What it does

These endpoints expose the collection as open, standards-based data for integration:

- **/api/v1/graph** serves the catalogue as a linked-data graph, with **content negotiation** so a client can request its preferred RDF serialization via the `Accept` header.
- **/.well-known/void** publishes a **VoID** (Vocabulary of Interlinked Datasets) description, the conventional place for tools to discover what the dataset is and how to access it.
- The **graph sitemap** lists the linked-data resources, helping crawlers and aggregators find and traverse them systematically.
- **/api/oai** provides an **OAI-PMH** endpoint, the long-established protocol for harvesting metadata into aggregators and union catalogues.

This gives integrators multiple, complementary front doors: a graph for linked-data clients and OAI-PMH for traditional metadata harvesters.

---

## How to use it

1. **Fetch the graph:** request **/api/v1/graph** and set an `Accept` header for the serialization you want (for example `https://your-site.example/api/v1/graph`). The server negotiates and returns the matching format.
2. **Discover the dataset:** read **/.well-known/void** (for example `https://your-site.example/.well-known/void`) to learn what the dataset contains and where its access points are.
3. **Crawl systematically:** use the **graph sitemap** to enumerate the linked-data resources rather than guessing URLs.
4. **Harvest metadata:** point an OAI-PMH harvester at **/api/oai** (for example `https://your-site.example/api/oai`) and use the standard verbs (such as `Identify`, `ListRecords`, `GetRecord`) to pull records.
5. Combine them as needed - use VoID and the sitemap to discover, the graph for linked data, and OAI-PMH for bulk metadata harvesting.

---

## Good to know

- Content negotiation means one URL can serve several formats - set the `Accept` header rather than expecting a fixed serialization.
- **/.well-known/void** follows the well-known-URI convention, so generic dataset tools can find it without site-specific configuration.
- OAI-PMH is widely supported by aggregators and union catalogues, making **/api/oai** the simplest route for established harvesting workflows.
- These surfaces expose published, openly shareable data and honour the platform's access rules - restricted material is not emitted.
- Examples here use `your-site.example` as a placeholder; substitute your own site's address.
