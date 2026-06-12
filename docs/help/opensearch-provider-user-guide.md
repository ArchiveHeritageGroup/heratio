# OpenSearch search provider

Heratio publishes an **OpenSearch 1.1 description document** so that a web
browser, a library discovery layer, or a federated search aggregator can add
your catalogue as a **search provider**. Once added, a visitor can type a query
into their browser's address/search bar (or an aggregator's "search these
sources" list) and have it run against your catalogue directly.

## Where it lives

The description document is served at:

```
GET /opensearch.xml
```

It is returned with the registered media type
`application/opensearchdescription+xml; charset=UTF-8`.

The path is a **dotted** path (`.xml`), so it never collides with the
single-segment archival-record `/{slug}` page - a record slug can never contain
a dot, so the two can never be confused.

## What it advertises

The document tells the consumer two things:

1. **Where to send a free-text search (HTML).** This points at the **real**
   public catalogue search:

   ```
   /glam/browse?query={searchTerms}
   ```

   `{searchTerms}` is the OpenSearch placeholder that the browser/aggregator
   replaces with whatever the visitor typed. `query` is the exact parameter the
   GLAM browse page reads, so the results are identical to typing the same words
   into the on-site search box.

2. **Where to get machine-readable results (JSON), when available.** If the
   public read API is installed, the document also advertises:

   ```
   /api/v1/informationobjects/search?query={searchTerms}
   ```

   This returns published records as JSON for an aggregator that prefers
   structured results. It is only advertised when that route actually exists, so
   the document never points at a dead endpoint.

The institution / site name shown in the provider (its short name and title)
comes from your existing **Site information** setting (`siteTitle`). The host is
taken from the live request URL, so the same code works on every deployment and
every market without hardcoding a domain.

## How a visitor adds your catalogue

- **Browsers** that support OpenSearch detect the provider automatically when
  they load a page from your site (an autodiscovery `<link rel="search">` is
  added to the page head), and offer to add it to the search bar.
- **Aggregators / discovery layers** are usually given the URL of the
  description document directly: paste `https://your-site/opensearch.xml` into
  the aggregator's "add a search target" form.

## Autodiscovery link

So that browsers can find the provider without any manual step, Heratio adds a
small autodiscovery tag to the `<head>` of HTML pages:

```html
<link rel="search"
      type="application/opensearchdescription+xml"
      href="/opensearch.xml"
      title="Your institution catalogue">
```

This is injected automatically and safely; it adds nothing visible to the page
and changes no other markup.

## Read-only and safe by design

- The endpoint is **read-only**. It performs no writes, runs no schema changes,
  and adds no new tables - it only reads the existing `siteTitle` setting.
- It **never errors**. If a setting is missing or a lookup fails, it falls back
  to neutral defaults; in the worst case it returns a minimal but still valid
  OpenSearch document rather than a 500.
- It is **international**. No language is baked into the search target, so it
  works the same for every market.

## Troubleshooting

- *The browser does not offer to add the provider.* Confirm the page you are on
  is served as HTML and that `GET /opensearch.xml` returns 200 with the
  `application/opensearchdescription+xml` content type.
- *The search runs but returns nothing.* The provider targets the same search as
  the on-site box; check that the query also returns results when typed into the
  catalogue search directly.
- *No JSON results option appears in an aggregator.* The JSON template is only
  advertised when the public read API route is installed.
