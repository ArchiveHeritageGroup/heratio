# Rate limits + cache TTLs

External authority APIs are not free. Every adapter respects a per-source
rate limit and a per-source cache TTL. Rate limits keep us inside the
provider's terms-of-service; cache TTLs keep latency under control and
reduce upstream churn.

## How they interact

```mermaid
sequenceDiagram
    participant UI as Pre-fill UI
    participant PE as PrefillEngine
    participant CA as Cache (ahg_authority_lookup_cache)
    participant AD as Adapter (e.g. VIAF)
    participant RL as Rate limiter (per-source token bucket)
    participant API as External API

    UI->>PE: search("Mzilikazi", PERSON)
    PE->>CA: lookup(viaf, PERSON, "Mzilikazi")
    alt cache hit + within TTL
        CA-->>PE: cached payload
        PE-->>UI: payload + license_note
    else cache miss / stale
        PE->>RL: acquire(viaf)
        alt within rate limit
            RL-->>PE: ok
            PE->>AD: search(...)
            AD->>API: HTTP GET
            API-->>AD: JSON
            AD-->>PE: normalised result
            PE->>CA: store(viaf, ..., ttl_seconds)
            PE-->>UI: result + license_note
        else over rate limit
            RL-->>PE: deferred
            PE-->>UI: (silent skip; fall through to next adapter)
        end
    end
```

## Per-source defaults

| source     | rate_limit_per_min | cache_ttl_seconds | notes                                                        |
|-----------|--------------------|-------------------|--------------------------------------------------------------|
| viaf      | 60                 | 2_592_000  (30 d) | OCLC: "respect server", informal cap ~1/sec sustained        |
| wikidata  | 60                 | 2_592_000  (30 d) | Wikimedia: be polite, set User-Agent                          |
| geonames  | 60                 | 2_592_000  (30 d) | Free tier 1000 req/hour/username; we self-throttle to 60/min |
| tgn       | 30                 | 7_776_000  (90 d) | Getty SOAP; rate-conservative                                |
| gnd       | 60                 | 2_592_000  (30 d) | lobid.org best-effort                                        |
| isni      | 30                 | 7_776_000  (90 d) | OCLC SRU; rate-conservative                                  |
| sagnc     | 30                 | 7_776_000  (90 d) | scrape-based; long TTL                                       |

## Overriding

Per-source overrides via `ahg_settings`:

```sql
UPDATE ahg_settings
   SET setting_value = '120'
 WHERE setting_key   = 'lookup.viaf.rate_limit_per_min';

UPDATE ahg_settings
   SET setting_value = '604800'      -- 7 days
 WHERE setting_key   = 'lookup.viaf.cache_ttl_seconds';
```

Both keys are read on each `PrefillEngine` call (no restart needed).

## Cache hit/miss inspection

```bash
sudo -u www-data php artisan auth-res:cache-stats
```

Sample output:

```
ahg_authority_lookup_cache: 2 rows
  viaf:     1 entries, oldest 2026-05-19, newest 2026-05-19, types PERSON
  wikidata: 1 entries, oldest 2026-05-19, newest 2026-05-19, types PERSON
  geonames: 0 entries
  ...
```

## Clearing the cache

```bash
# Single source, with confirm prompt
sudo -u www-data php artisan auth-res:cache-clear --source=viaf

# Single source, no prompt (cron-safe)
sudo -u www-data php artisan auth-res:cache-clear --source=viaf --force

# All sources, no prompt
sudo -u www-data php artisan auth-res:cache-clear --all --force
```

The next live lookup will re-populate the row with a fresh `retrieved_at`
and the current `ttl_seconds`.

## When the rate limiter denies a request

The adapter logs (`Log::info('lookup rate-limited', ...)`) and returns no
result. `PrefillEngine` treats "no result" as "fall through to next
source". The user sees a slightly slower pre-fill but no error - the
field is then filled by the next adapter in `lookup.precedence`.

## Capacity planning

A pre-fill on a brand-new authority typically touches 2-3 sources before
finding the field; with the defaults above, that's ~2-3 API calls per
new-authority creation. The cache cuts repeat lookups to zero. Realistic
steady state for a daily-active deployment with ~100 new-authority
creations per day: ~50 API calls per source per day, well under the
free-tier ceilings.
