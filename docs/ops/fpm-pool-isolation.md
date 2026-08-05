# php-fpm pool isolation + saturation monitor (#1446)

**Problem.** On 2026-08-04 a CPU-heavy co-tenant job jammed the single shared
php-fpm pool (`/run/php/php8.3-fpm.sock`, `[www]`, ~100 children) and **every**
site on the host 502'd for hours. One shared pool = any slow site / runaway
request / heavy co-tenant job takes down all sites, with no isolation and no
warning until the whole pool is exhausted.

**Fix (shipped 2026-08-05, v1.154.515).** Two things:

1. **Per-site pools for the production sites** so a co-tenant can't starve them.
2. **A saturation monitor** so pool exhaustion is caught before it's an outage.

These are **host-level** artifacts (php-fpm pool.d + nginx vhosts + cron). This
doc is the reproducible record + rollback; the pool configs live on the host,
the monitor script ships in the repo (`bin/fpm-pool-monitor`).

## Pools

The shared `[www]` pool (~47 co-tenant sites) is left at `pm.max_children = 100`
and untouched. Two dedicated pools were added **additively** (own sockets,
mirroring www.conf's directives, `pm.status_path = /fpm-status` so the monitor
sees them):

| pool | socket | max_children | serves |
|---|---|---|---|
| `www` | `/run/php/php8.3-fpm.sock` | 100 | all co-tenant sites (unchanged) |
| `atom` | `/run/php-fpm.atom.sock` | 15 | atom (pre-existing) |
| `heratio` | `/run/php-fpm.heratio.sock` | 25 | heratio.org (prod) |
| `sasa` | `/run/php-fpm.sasa.sock` | 15 | sasaarchive.theahg.co.za |

Pool files: `/etc/php/8.3/fpm/pool.d/heratio.conf`, `.../sasa.conf` (each is a
copy of the `[www]` directives with a new name, socket and worker budget). After
adding a pool file: `php-fpm8.3 -t && systemctl reload php8.3-fpm`.

## vhost repoints

- `heratio.org.conf` `fastcgi_pass` -> `unix:/run/php-fpm.heratio.sock`
- `sasaarchive.theahg.co.za.conf` `fastcgi_pass` -> `unix:/run/php-fpm.sasa.sock`

Backups: `*.bak-1446-<timestamp>` beside each vhost. Validate + apply:
`nginx -t && systemctl reload nginx`.

## Saturation monitor

`bin/fpm-pool-monitor` (runs as `www-data`, read-only) queries each pool's
`/fpm-status` over its socket via `cgi-fcgi` and drops a Workbench notification
when a pool nears exhaustion: active >= 85% of `pm.max_children`, OR listen queue
> 0, OR the `max children reached` counter climbs. Debounced (900s per pool
unless the ceiling is hit again). Pools watched are the `POOLS=(...)` array in the
script; a pool whose socket is absent is skipped.

Cron (`/etc/cron.d/heratio-fpm-monitor`, every minute):
```
* * * * * www-data /usr/share/nginx/heratio/bin/fpm-pool-monitor >> /usr/share/nginx/heratio/storage/logs/fpm-pool-monitor.log 2>&1
```

Thresholds are env-overridable: `FPM_ALERT_PCT`, `FPM_ALERT_COOLDOWN`,
`FPM_ALERT_USER`.

## Standing rule (mitigation already in force)

Model / heavy-compute batches (NLLB, Whisper, embeddings, ...) run on the GPU
node **192.168.0.76**, never the shared web/db host. This is what caused the
2026-08-04 outage; keeping compute off the box is the primary prevention, the
pools + monitor are defence in depth.

## Rollback

Fully reversible, per site, in under a minute:

1. **vhost**: restore `*.bak-1446-*` (or set `fastcgi_pass` back to
   `unix:/run/php/php8.3-fpm.sock`), then `nginx -t && systemctl reload nginx`.
2. **pool**: `rm /etc/php/8.3/fpm/pool.d/{heratio,sasa}.conf` then
   `php-fpm8.3 -t && systemctl reload php8.3-fpm`.
3. **monitor**: `rm /etc/cron.d/heratio-fpm-monitor`.

## Recovery during an actual saturation event

`systemctl restart php8.3-fpm` clears jammed workers across all pools; remove the
CPU hog. The monitor's notification message carries this reminder.

## Future tuning (not done - needs a capacity decision)

Optionally shrink `[www]` `pm.max_children` 100 -> ~60 so total workers stay
bounded (the reserved 25+15 for prod then come out of the budget rather than
adding to it). Left additive for now to avoid reducing headroom for the ~47
co-tenant sites without an explicit call.
