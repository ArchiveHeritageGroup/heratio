# OpenSearch description document (search-provider autodiscovery)

Heratio exposes an OpenSearch 1.1 description document at `GET /opensearch.xml`
so a browser or a federated discovery aggregator can register the catalogue as a
search provider. The document targets the real public catalogue search, degrades
to a minimal valid document rather than 500ing, and is inherently safe from the
`/{slug}` archival-record catch-all because the path is dotted.

## Files (all in `packages/ahg-core`, none locked)

- `src/Controllers/OpenSearchController.php` - builds + serves the document.
- `src/Middleware/InjectOpenSearchLink.php` - adds the autodiscovery
  `<link rel="search">` into the `<head>` of HTML responses.
- `routes/web.php` - `GET /opensearch.xml` -> `OpenSearchController@index`
  (name `opensearch.description`), registered next to the other single-segment
  public routes (`/explore`, `/open-data`, `/accessibility-statement`).
- `src/Providers/AhgCoreServiceProvider.php` - pushes the middleware onto the
  `web` group inside `app->booted()`, exactly like `InjectSplatViewer`.

## The real search target (the load-bearing fact)

The free-text public catalogue search is the GLAM browse page:

- Route: `GET /glam/browse` (`packages/ahg-display/routes/web.php`, name
  `glam.browse`).
- Parameter: `query`. `DisplayController::browse()` reads it as
  `$this->queryFilter = $request->input('query');` (around line 209 of
  `packages/ahg-display/src/Controllers/DisplayController.php`). It then strips a
  wildcard-only value and feeds the rest into the FULLTEXT / ES search builder.

So the OpenSearch HTML `<Url>` template is:

```
{base}/glam/browse?query={searchTerms}
```

## Optional JSON search surface

A public, no-auth, published-only JSON search exists:

- Route: `GET /api/v1/informationobjects/search`
  (`packages/ahg-api/routes/api.php`, inside the `api/v1` group with
  `throttle:60,1` but NOT behind `api.auth`).
- Parameter: `query` (then `q`).
  `InformationObjectApiController::search()` reads
  `$request->get('query') ?? $request->get('q')` and filters to publication
  status 160 (published).

The controller advertises a second `<Url type="application/json">` template
pointing here, **only when the route is actually registered** (checked by
scanning `Route::getRoutes()` for the `api/v1/informationobjects/search` GET
uri). If the API package is absent the JSON `<Url>` is simply omitted.

## Document fields

- `ShortName` (<=16 chars) and `LongName` derived from the `siteTitle` setting.
- `Description`, `Tags`, `Contact` (operator `emailAddress` setting if a valid
  e-mail, else `webmaster@{host}`).
- `Image` -> `/favicon.ico`.
- `Url type="text/html"` -> the GLAM browse template above.
- `Url type="application/json"` -> the v1 search template (when present).
- `Url type="application/opensearchdescription+xml" rel="self"` ->
  `/opensearch.xml`.
- `InputEncoding` / `OutputEncoding` UTF-8, `SyndicationRight`, `AdultContent`.

The site name comes from the `setting` / `setting_i18n` `siteTitle` value (the
same value the theme header renders), culture `en`, with a neutral `Heratio`
fallback. The host is derived from `url()`, never hardcoded.

## Safety properties

- **Catch-all-safe by construction**: the `.xml` dot means the single-segment
  `/{slug}` route (constraint `[a-z0-9][a-z0-9-]*`) can never match it. No
  boot-order or exclusion-list dependency is relied on.
- **Never 500s**: `index()` wraps `buildDocument()` in a guard and falls back to
  `minimalDocument()`; each setting lookup is individually try/caught.
- **Read-only**: only SELECTs against `setting` / `setting_i18n`; no writes, no
  ALTER, no new table.
- **Correct XML**: all dynamic values escaped with
  `htmlspecialchars(... ENT_QUOTES | ENT_XML1, 'UTF-8')`; the `{searchTerms}`
  macro is preserved (braces are not altered by escaping).
- **Content-Type**: `application/opensearchdescription+xml; charset=UTF-8`
  (the bare media type is used inside the `rel="self"` `type` attribute).

## Autodiscovery middleware

`InjectOpenSearchLink` adds, immediately before the first `</head>` of a
successful `text/html` GET response, one tag:

```html
<link rel="search" type="application/opensearchdescription+xml"
      href="{base}/opensearch.xml" title="{siteTitle} catalogue">
```

It is idempotent (skips if the link is already present), touches nothing but the
head insertion point, leaves non-HTML / headless / non-200 responses untouched,
and is fully guarded so it can never break a page. This is the documented
response-middleware cousin of the `View::composer` injection pattern used for
locked callers (so the locked theme `<head>` is never edited).

## Verification done

- `php -l` clean on all four touched files.
- Standalone render harness: the built document is well-formed XML in the
  OpenSearch 1.1 namespace; the HTML `<Url>` template is
  `/glam/browse?query={searchTerms}` with the macro intact; the JSON `<Url>` is
  present when the route exists and omitted when absent; special characters in
  the site name are correctly XML-escaped; the DB-down and route-absent branches
  still produce well-formed documents; `minimalDocument()` is well-formed.
- Middleware harness: injects exactly once before `</head>`, is idempotent,
  leaves JSON / headless responses untouched, and preserves the page body.
