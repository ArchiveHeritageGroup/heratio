# AHG Central — Fleet Monitoring

AHG Central is the fleet registry, heartbeat and error-monitoring service for
the AtoM / Heratio estate. Every install can report into it; the fleet is
viewed from the AHG Workbench.

- **Service:** Flask app at `/opt/ai/central/app.py`, port 5060, fronted by
  `https://central.theahg.co.za`. Registry is SQLite (`/opt/ai/central/central.db`).
- **Monitored from:** the AHG Workbench admin → **AHG Central** tab, which
  proxies the service's `GET /api/v1/admin/fleet` feed. Fleet events (a new
  instance enrolling, an instance going silent, a burst of critical errors)
  also drop notifications into the Workbench bell.
- **Issue:** `ArchiveHeritageGroup/heratio#127`.

## One server, three reporter clients

AHG Central is a single server with one API contract. Three client
implementations report into it, one per platform tier — because the estate
spans two runtimes (Laravel and Symfony 1.4) and two error stores.

| Platform | Client | Commands | Version reported | Error source |
|---|---|---|---|---|
| Heratio (Laravel) | `packages/ahg-core` — `AhgCentralService` | `artisan ahg:central-*` | `version.json` (1.x) | `ahg_error_log` table |
| AtoM Heratio (Symfony + atom-framework) | `ahgCorePlugin` — `AhgCore\Services\AhgCentralService` | `php symfony central:*` | `atom-ahg-plugins/version.json` (3.x) | `ahg_error_log` table |
| Vanilla AtoM **or** AtoM Heratio | `ahgCentralPlugin` (standalone, universal, v1.1.0) | `php symfony ahg:central-*` | auto: 2.x vanilla / 3.x AtoM Heratio | auto: `qubit_prod.log` / `ahg_error_log` |

Versions are grouped by major component for drift detection: Heratio 1.x,
vanilla AtoM 2.x, AtoM Heratio 3.x.

## What each client does

Three concerns, one service class per client:

- **Heartbeat** — a daily `POST /api/v1/heartbeat` carrying `site_id`,
  version and timestamp. Proof of life + version.
- **Error sync** — `POST /api/v1/errors` with redacted recent errors, sent as
  a full replace. A **separate opt-in** on top of the master switch.
- **Zero-touch enrolment** — *not* a client action and *not* a toggle. The
  server auto-creates a registry row the first time it sees an authenticated
  call from an unknown `site_id` that presents the shared fleet key
  (trust-on-first-use). It happens once, on the first heartbeat (or first
  error POST), then never again.

`site_id` is operator-set, else auto-derived: `heratio-<hostname>` on Heratio,
`atom-<hostname>` on AtoM. When several installs share one host, set a distinct
`site_id` to avoid collisions.

## On / off model

Two settings, a master and a sub-toggle — not two independent switches:

```
ahg_central_enabled = 0   →  totally silent (heartbeat, errors, ping, enrolment all off)
ahg_central_enabled = 1   →  heartbeat + ping on
      └ ahg_central_error_sync = 0  →  errors off
      └ ahg_central_error_sync = 1  →  errors also on
```

`ahg_central_enabled` is the master switch. Each client's service re-checks it
inside every method, so a disabled install cannot emit anything by any path —
the scheduled job still runs, but it self-gates and exits immediately.
`ahg_central_error_sync` only ever *adds* error shipping on top of an
already-enabled install.

## How reporting is triggered

- **Heratio (Laravel)** — `AhgCoreServiceProvider` registers the schedule
  (`ahg:central-heartbeat` daily 05:00, `ahg:central-sync-errors` hourly); the
  `heratio-schedule` cron runs `schedule:run` every minute and drives it.
  Auto-wired — nothing to set up per install beyond the one cron.
- **AtoM Heratio (`ahgCorePlugin`)** — ships the `central:*` Symfony tasks but
  wires **no scheduler**. Each install needs an explicit cron line, e.g.
  `php symfony central:heartbeat`. With no cron, nothing reports even when the
  master switch is on.
- **`ahgCentralPlugin`** — provides `ahg:central-*` Symfony tasks for cron,
  **plus** an opportunistic web-traffic heartbeat fallback (throttled to ~once
  per 20h, deferred so it never delays a page) that covers a missed cron tick.

## ahgCentralPlugin — the universal Symfony reporter

A standalone Symfony 1.4 AtoM plugin (v1.1.0). One plugin for the whole
Symfony side of the fleet — vanilla AtoM **and** AtoM Heratio — auto-detecting
the environment at runtime. Modifies no base AtoM file; depends on no other
plugin and no atom-framework.

**Auto-detection:**

- *Vanilla AtoM* — version from `qubitConfiguration::VERSION` (2.x); errors
  tailed from `log/qubit_prod.log`.
- *AtoM Heratio* — detected by `atom-ahg-plugins/version.json`; version from
  that file (3.x); errors read from the `ahg_error_log` table via `QubitPdo`.

On an AtoM Heratio box it is a clean drop-in replacement for `ahgCorePlugin`'s
built-in reporter — same error source, same version tier. Schedule only **one**
heartbeat cron there (this plugin's `ahg:central-heartbeat`, not
`ahgCorePlugin`'s `central:heartbeat`).

**Install (the normal AtoM way):** drop the plugin into `plugins/`, enable via
Admin → Manage plugins, `php symfony cc`, then configure at
`/index.php/ahgCentral` (enable, fleet key, optional error sync), and add the
cron lines shown on that page.

**CLI tasks:** `ahg:central-ping` (test + show detected environment),
`ahg:central-heartbeat`, `ahg:central-sync-errors` (`--dry-run` to preview).

## Configuration

Runtime settings carry the `ahg_central_*` prefix:

| Key | Purpose |
|---|---|
| `ahg_central_enabled` | master switch |
| `ahg_central_error_sync` | separate opt-in for error shipping |
| `ahg_central_api_url` | API base (default `https://central.theahg.co.za/api/v1`) |
| `ahg_central_api_key` | shared fleet enrolment key (Bearer token) |
| `ahg_central_site_id` | this install's id (else auto from hostname) |

Storage differs by platform: Heratio Laravel uses the `ahg_settings` table;
the Symfony clients use AtoM's `setting` table. Both Symfony clients share the
same keys, so configuration carries over between them. Deploy defaults come
from `.env` / `config/app.yml`. Configured via the **AHG Integration**
settings page (Heratio / AtoM Heratio) or the `ahgCentralPlugin` admin page.

The shared fleet enrolment key is `CENTRAL_ENROLMENT_KEY` on the server
(`/etc/default/ahg-central`); every install carries the same value as its
Bearer token. It is a secret — never commit it to a public repository.

## Privacy / redaction

Reporting is best-effort and fails soft — a failure is logged and ignored, and
never affects local functionality. Before any error text leaves a box it is
redacted: email addresses and runs of 9+ digits are masked, URL query strings
are stripped. Stack traces, client IPs, user agents and user ids are never
sent. Error sync is a deliberate separate opt-in for this reason.

## API contract

`{api_url}` defaults to `https://central.theahg.co.za/api/v1`.

- `GET  /ping` — public liveness probe.
- `POST /heartbeat` — authenticated; records the site alive + version;
  auto-enrols an unknown site presenting the fleet key.
- `POST /errors` — authenticated; ingests a batch of redacted error rows.
- `GET  /admin/fleet` — admin-token feed of the whole fleet (the Workbench
  monitor's data source).

Auth headers: `Authorization: Bearer <fleet-key>` and `X-Heratio-Site-Id:
<site_id>`.
