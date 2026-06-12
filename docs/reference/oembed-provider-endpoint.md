# oEmbed provider endpoint (paste a record URL, get a rich card)

Heratio is an oEmbed 1.0 provider. [oEmbed](https://oembed.com/) is the open
standard that lets ANY consumer site (a CMS, a chat app, a blog editor, a learning
platform) turn a pasted record URL into a rich embeddable card - the same mechanism
that auto-embeds a YouTube or Flickr link. A consumer calls:

```
GET /oembed?url={recordUrl}&format=json|xml&maxwidth=&maxheight=
```

The endpoint resolves the `url` to a PUBLISHED archival description, and returns an
oEmbed `rich` response carrying a small, self-contained, escaped HTML card that
links back to the record. Read-only, bounded to ONE record, never 500s.

## Files (all in `packages/ahg-core`, none locked)

- `src/Services/OembedResolverService.php` - the URL -> record resolver (the
  load-bearing "which record is this?" logic).
- `src/Controllers/OembedController.php` - builds + serves the oEmbed JSON / XML
  response and the embeddable HTML card.
- `src/Middleware/InjectOembedLink.php` - adds the autodiscovery
  `<link rel="alternate" type="application/json+oembed">` to record-shaped HTML
  pages. DB-FREE (href built from the current request URL only).
- `routes/web.php` - `GET /oembed` -> `OembedController@index` (name
  `oembed.endpoint`), registered next to the other single-segment public routes
  (`/explore`, `/open-data`, `/accessibility-statement`).
- `src/Providers/AhgCoreServiceProvider.php` - pushes the middleware onto the
  `web` group inside `app->booted()`, exactly like `InjectOpenSearchLink`.

## URL -> record resolution + published gate (the load-bearing fact)

A Heratio archival-record public URL is a SINGLE-segment slug path served by the
`/{slug}` catch-all in `ahg-information-object-manage`, e.g.
`https://host/title-of-object`. `OembedResolverService::resolve()`:

1. Parses the consumer `url`. It must be a parseable http/https URL whose host
   matches this site's host (`url('/')` host); an off-site URL is rejected.
2. Takes the FIRST path segment as the candidate slug, lower-cased and trimmed. A
   leading `index.php/` is stripped first. The segment must match
   `^[a-z0-9][a-z0-9-]*$` (the same shape the `/{slug}` route constrains), and a
   known non-record prefix (`admin`, `api`, `glam`, `oembed`, ...) is rejected.
3. Looks the slug up and confirms a PUBLISHED, non-root record:

```
SELECT io.id, i.title
  FROM slug s
  JOIN information_object io ON io.id = s.object_id
  JOIN status st ON st.object_id = io.id
                AND st.type_id = 158 AND st.status_id = 160   -- publication = Published
  LEFT JOIN information_object_i18n i
         ON i.id = io.id AND i.culture = io.source_culture     -- authoritative title
 WHERE s.slug = ? AND io.id > 1                                -- exclude synthetic root
 LIMIT 1
```

- **Published gate (confirmed)**: `status.type_id = 158` (publication-status
  taxonomy) AND `status.status_id = 160` (Published). Same constant pair used by
  `RecentlyAddedService`, `OpenSearchController`, `CollectionOverviewService`, etc.
- **Root excluded**: `io.id > 1`.
- **Title**: `information_object_i18n.title` on the record's OWN `source_culture`
  (the authoritative title, not a stray translation). Empty title -> `Untitled record`.

A missing / unparseable / off-site / unknown / draft `url` resolves to `null` so the
controller emits a clean oEmbed 404 - never a record the public may not see, never a
500.

### Optional fields (cheap, guarded)

- **author_name** = the creator: `event.object_id = id AND event.type_id = 111`
  (creation) joined to `actor_i18n.authorized_form_of_name` (the record's own
  culture preferred). Absence is fine.
- **thumbnail_url** = the `usage_id = 141` THUMBNAIL child of the record's master
  digital object (`digital_object.object_id = id`); web path = `path + name` via
  `url()`. No file IO. `digital_object` has no width/height columns on this schema,
  so `thumbnail_width`/`thumbnail_height` use the card strip size (120x120) only
  when a thumbnail is present.

## oEmbed response fields

`rich`-type response (oEmbed 1.0):

| field | value |
|---|---|
| `version` | `1.0` |
| `type` | `rich` |
| `title` | record title (own-culture) |
| `provider_name` | `siteTitle` setting (theme header value), neutral `Heratio` fallback |
| `provider_url` | `url('/')` |
| `author_name` / `author_url` | creator + record URL (only when a creator resolves) |
| `thumbnail_url` / `thumbnail_width` / `thumbnail_height` | only when a thumbnail exists |
| `width` / `height` | card geometry, clamped to `maxwidth` / `maxheight` hints |
| `cache_age` | `86400` |
| `html` | the self-contained embeddable card (see below) |

## The embeddable HTML card + escaping

`html` is a compact, inline-styled, dependency-free `<figure>`-style `<div>` that
links back to the record: optional thumbnail strip, the title, an optional
`Created by {creator}` line, and a provider footer. No external script or stylesheet,
so it is safe to drop into a host page. `maxwidth` caps the card `max-width`.

**Escaping**: every dynamic value (title, creator, URLs, thumbnail src) is escaped
with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` before it enters the card markup,
so a record title like `</title><script>...` becomes inert `&lt;script&gt;` text and
can never inject markup or attributes. In the XML document the whole card plus every
field is escaped with `ENT_QUOTES | ENT_XML1`, so the `<oembed>` document stays
well-formed even with an injection-laden title.

## Formats

- `format=json` (default): `application/json`, `Access-Control-Allow-Origin: *`
  (CORS-open so a consumer's browser-side fetch succeeds), `Cache-Control:
  public, max-age=86400`.
- `format=xml`: `text/xml; charset=UTF-8`, a well-formed `<oembed>` document with
  the same fields, CORS-open.
- An unsupported `format` -> HTTP `501` oEmbed error.

## Error handling (never 500s)

- Missing `url` -> `400` `{ "error": "The url parameter is required." }`.
- Unknown / unpublished / off-site `url` -> `404`
  `{ "error": "No published record was found for that URL." }`.
- Unsupported `format` -> `501`.
- Any unexpected failure is caught and degrades to the `404` oEmbed error.
- In `format=xml`, the error is a well-formed `<oembed><error>...</error></oembed>`.

## Route + catch-all safety

`GET /oembed` is a SINGLE-segment public path; the record URL travels in the `?url=`
query string, so the path itself is just `/oembed`. `ahg-core` boots early, so this
route is registered before the single-segment `/{slug}` archival-record catch-all in
`ahg-information-object-manage` and wins the match (first-registered route wins - the
same mechanism `/explore`, `/open-data`, `/recent`, `/accessibility-statement` rely
on). A normal record slug still resolves, because the catch-all only matches a
single-segment path that no earlier route has already claimed.

## Autodiscovery middleware (DB-free)

`InjectOembedLink` adds, immediately before the first `</head>` of a successful
single-segment (record-shaped) `text/html` GET response, one tag:

```html
<link rel="alternate" type="application/json+oembed"
      href="{base}/oembed?url={thisPageUrl}" title="oEmbed">
```

It is **DB-free by design**: the href is built purely from `$request->fullUrl()` -
there is NO record lookup, no publication check, no setting read in the middleware.
The `/oembed` endpoint does the (guarded, read-only) resolution when a consumer
actually calls it; an autodiscovery link on a non-record page is harmless (the
endpoint returns a clean 404). It only touches single-segment slug-shaped paths
(admin / multi-segment / dotted paths are skipped, read from the request alone), is
idempotent (skips if the link is already present), leaves non-HTML / headless /
non-200 responses untouched, and is fully guarded so it can never break a page. This
is the documented response-middleware cousin of the `View::composer` injection
pattern, registered exactly like `InjectOpenSearchLink`.

## Safety properties

- **Read-only**: only the resolver's bounded SELECTs (slug / status /
  information_object_i18n / event / actor_i18n / digital_object). No writes, no
  ALTER, no new table.
- **Bounded**: one record per request.
- **Never 500s**: validated format, guarded resolution, clean oEmbed errors.
- **Host from `url()`**, never hardcoded.
- **International**: culture-neutral; copy internationalised via `__()`.

## Verification done

- `php -l` clean on all five touched files.
- Resolver against the live DB: a published slug resolves (id/slug/title/url, plus
  author_name and thumbnail_url where present); an unknown slug, an `/admin/...`
  URL, an off-site host, an empty string and a garbage string all resolve to
  `null`.
- Controller: `format=json` returns 200 `application/json` with CORS `*` and the
  full field set (width clamped to `maxwidth=320`, thumbnail with dimensions, the
  self-contained card); `format=xml` returns 200 `text/xml`, a `simplexml`-valid
  `<oembed>` document with the card escaped inside `<html>` and `author_name` /
  `author_url` present; missing url -> 400, unknown url -> 404, unsupported format
  -> 501, and the xml error document is well-formed.
- Escaping: a title carrying `</title><script>...` and an author carrying
  `<img onerror=...>` are escaped to inert `&lt;...&gt;` text in the card, and the
  XML stays well-formed.
- Middleware: injects exactly once before `</head>` on a single-segment record-
  shaped HTML page (href = `/oembed?url={page}`, built from the request only),
  skips multi-segment `/admin/...` pages, skips JSON and non-200 responses, and is
  idempotent. No DB query is made in the middleware.
- The live archival-record page still renders (baseline unaffected; the worktree
  changes are not deployed).
