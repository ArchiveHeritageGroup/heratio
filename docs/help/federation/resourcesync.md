> Heratio Help Center article. Category: Integration. Slug: federation-resourcesync.

# ResourceSync Federation Endpoint

Heratio publishes its archival inventory as a **ResourceSync 1.1 Source**, a
sitemap-style protocol that lets external aggregators harvest your records
without speaking OAI-PMH. The two surfaces are complementary — operators
don't have to choose between them.

## When to use ResourceSync vs OAI-PMH

| You want to... | Use |
| --- | --- |
| Federate with another archive / library that already speaks OAI | OAI-PMH (`/oai`) |
| Federate with a research-data, IIIF, or web-archiving aggregator | ResourceSync |
| Let a search engine crawl your inventory as a sitemap | ResourceSync |
| Harvest with metadata bodies (Dublin Core, EAD, MODS, MARCXML) | OAI-PMH |
| Harvest a lightweight URL inventory without metadata bodies | ResourceSync |

## Discovery

Any ResourceSync aggregator starts at the well-known URL:

```
https://YOUR-SITE/.well-known/resourcesync
```

That points to a CapabilityList which lists the two capabilities Heratio
offers: **ResourceList** (full inventory) and **ChangeList** (recent
updates + deletions).

## What's exposed

Heratio publishes archival records that are:

- Marked **published** in the publication-status taxonomy
- Not the synthetic top-level root node

This is the same filter the OAI-PMH endpoint uses. A record hidden from one
is hidden from the other.

## ChangeList horizon

The ChangeList only reports records changed in the **last 30 days** by
default. Aggregators that poll more often than that will never miss a
change. Aggregators that poll less often should fall back to the full
ResourceList every now and then.

Operators can change the horizon in the environment:

```env
RESOURCESYNC_CHANGELIST_DAYS=60
```

## Configuration

Settings live in two places:

1. **Page size** — honours the OAI `resumption_token_limit` setting (Admin
   → Settings → OAI) so you only have to tune one knob for both
   protocols. Falls back to `RESOURCESYNC_PAGE_SIZE=1000` in `.env`.
2. **ChangeList horizon** — `RESOURCESYNC_CHANGELIST_DAYS=30` in `.env`.

## Tombstones

When a record is hard-deleted, run:

```bash
php artisan oai:mark-deleted <oai-local-identifier>
```

That inserts a tombstone row that both OAI-PMH and ResourceSync surface,
so aggregators can remove the record from their copies.

## Endpoints reference

| URL | What it returns |
| --- | --- |
| `/.well-known/resourcesync` | SourceDescription (discovery file) |
| `/resourcesync/capabilitylist.xml` | CapabilityList |
| `/resourcesync/resourcelist.xml?page=N` | ResourceList (paged) |
| `/resourcesync/changelist.xml?page=N` | ChangeList (paged, 30-day horizon by default) |

All four are XML, content type `application/xml; charset=UTF-8`, rate-limited
to 120 requests per minute per IP.

## Troubleshooting

- **Aggregator reports zero entries** — check at least one
  `information_object` has publication status = published. The same filter
  that hides records from OAI-PMH hides them here.
- **ChangeList misses recent edits** — `updated_at` on the `object` table
  may not be advancing. Check the audit-trail package or whichever writer
  path updated the record. Heratio uses MySQL's `ON UPDATE CURRENT_TIMESTAMP`
  semantics for `object.updated_at`.
- **Aggregator sees stale data** — ResourceSync documents are generated
  per-request from the live DB; there is no snapshot file. A stale view is
  a caching layer in front of Heratio (nginx / Cloudflare), not the
  endpoint itself.
- **429 Too Many Requests** — rate limit is 120 req/min/IP. A polite
  aggregator walking the full chain stays well under. A 429 is almost
  always a scraper.

## See also

- OAI-PMH endpoint help article
- Federation module overview
