> Heratio Help Center article. Category: Technical / Integration.

# Open Data Cookbook (developer worked examples)

## Overview

The platform publishes a developer-facing **cookbook** of copy-paste worked examples for consuming its open data: how to dereference an entity URI in the format you want, how to download the whole catalogue in bulk, how to harvest it, how to discover what is on offer, and how to load the data into common tools to run SPARQL locally.

Every example URL in the cookbook is resolved from the platform's live open-data surfaces, so the commands target this deployment's real URLs - you can copy a recipe and run it as-is. The cookbook is open data itself: no API key, read-only, cross-origin (CORS) open, and it performs no database access, so it cannot fail over record data.

---

## The endpoint

**GET /open-data/cookbook** (machine index at **GET /open-data/cookbook.json**)

A browser visiting `/open-data/cookbook` sees a readable HTML guide with each recipe and its command in a copy-paste block. A data client (or the explicit `.json` URL) gets a JSON `schema:TechArticle` whose `recipeGroups` array carries the same worked examples (`id`, `title`, `description`, `command`, `mediaType`) - so an agent can ingest the example set programmatically.

```bash
# JSON example index
curl -s "https://your-site.example/open-data/cookbook.json"
```

---

## What the cookbook covers

| Recipe group | What you learn |
|---|---|
| Content negotiation | Fetch a record / actor / term `/id/{slug}` URI as JSON-LD, Turtle or RDF/XML using the `Accept` header, or force a format with a `.jsonld` / `.ttl` / `.rdf` path suffix |
| Bulk download and harvest | Download the whole catalogue as CSV, JSON-LD, or one combined CIDOC-CRM Turtle graph; harvest incrementally over OAI-PMH (Identify, ListRecords + resumptionToken) |
| Discovery | Fetch the capabilities document, the VoID / DCAT description, the DCAT catalogue, the schema.org Dataset, and the crawl sitemap / seed |
| Load and query (locally) | Load a Turtle / CIDOC-CRM dump into rdflib (Python), validate / convert with Apache Jena `riot`, load into Jena TDB2 and run SPARQL, or serve it from a local Fuseki SPARQL server |
| Licence, attribution and CORS | The CC-BY-4.0 terms, attribution expectations, and the open-CORS note |

---

## There is no live SPARQL endpoint - run SPARQL locally

The platform does **not** host a SPARQL endpoint. The cookbook is honest about this: the SPARQL recipes show the **local** path. Download a bulk RDF dump (the CIDOC-CRM Turtle dump or the JSON-LD dataset), load it into a triple store you run yourself - rdflib, Apache Jena (`riot` / `tdb2` / Fuseki), or another store - and query it there.

```bash
# Download the combined CIDOC-CRM Turtle graph, then query it locally
curl -L -H "Accept: text/turtle" -o heritage-crm.ttl "https://your-site.example/data/cidoc-crm.ttl"

python3 - <<'PY'
import rdflib
g = rdflib.Graph()
g.parse('heritage-crm.ttl', format='turtle')
print(len(g), 'triples loaded')
for row in g.query('SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 10'):
    print(row)
PY
```

To get an actual SPARQL endpoint, run one yourself with Apache Jena Fuseki:

```bash
fuseki-server --file=heritage-crm.ttl /heritage
# then query http://localhost:3030/heritage/sparql
```

---

## Licence and attribution

All open-data surfaces are released under **CC-BY-4.0**. You may re-use the data freely, including commercially, provided you attribute the source and link back to the record URI. Every surface sends `Access-Control-Allow-Origin: *`, so you can fetch any of them directly from browser JavaScript with no proxy.

---

## Related surfaces

- **Capabilities index** - `GET /open-data/protocol` - the machine-discoverable list of every open surface.
- **DCAT catalogue** - `GET /data/catalog` - the same surfaces as a `dcat:Catalog`.
- **Maturity scorecard** - `GET /open-data/maturity` - how the offering grades against the 5-star scheme.

The cookbook is itself listed as a surface in the capabilities index (id `cookbook`), so it is discoverable from the protocol document and the DCAT catalogue.
