# AHG Central - Fleet Monitoring, Auto-Onboarding and Error Sync

**Summary.** AHG Central (`central.theahg.co.za`) is the cloud service every
Heratio install reports into. A new install **auto-commissions** itself with no
operator steps; it sends a daily **heartbeat** (alive + version) and, when the
operator opts in, an hourly **error-log sync**. The whole fleet is **monitored
from the AHG Workbench** admin area, which also raises a notification for each
fleet event. AHG Central is advisory and opt-in - it never gates a Heratio
install's own functionality, and air-gapped installs simply leave it off.

This is heratio#127. The Heratio client is `AhgCore\Services\AhgCentralService`.

## Zero-touch onboarding

A fresh Heratio install joins the fleet automatically:

- **Auto-install** - the Central client ships inside the `ahg-core` package, so
  deploying or updating Heratio deploys the client.
- **Auto-commission** - `bin/install` (and the Docker `init.sh`) seed
  `AHG_CENTRAL_API_URL` and `AHG_CENTRAL_API_KEY` (the shared fleet enrolment
  key) into `.env`. The service provider then seeds the `ahg_central_*` settings
  rows; `ahg_central_enabled` is switched on automatically **only when a fleet
  key is present**, so an install with no key stays quiet rather than phoning
  home. Existing operator-set values are never overwritten.
- **Auto-register** - `ahg_central_site_id` is derived from the machine
  hostname. On the install's first heartbeat, AHG Central creates its registry
  row on the spot (trust-on-first-use, gated by the fleet enrolment key). There
  is no manual registration step.
- **Auto-schedule** - the heartbeat (daily) and error-sync (hourly) run on the
  Laravel scheduler. No cron editing.

An operator who wants an air-gapped / sovereign install simply turns
`ahg_central_enabled` off in **Admin → AHG Settings → AHG Central**.

## Error-log sync (opt-in)

Error logs can carry stack traces, file paths, tokens and PII, so error-sync is
a **separate toggle** - `ahg_central_error_sync`, **off by default**. When on,
the `ahg:central-sync-errors` command pushes new `ahg_error_log` rows to AHG
Central each hour:

- **Redacted before they leave the building** - every message and URL is run
  through PII masking (email addresses and long number sequences masked) and
  URL query strings are stripped. The PII-heavy columns (stack trace, client
  IP, user agent, user id, request id) are never sent at all.
- **Incremental and idempotent** - a watermark (`ahg_central_last_error_id`)
  means each run sends only new rows; re-sends are de-duplicated on the Central
  side.

Turn it on per install in **Admin → AHG Settings → AHG Central** when the
institution consents to off-box error visibility.

## Where the fleet is monitored

The **AHG Workbench** is the fleet monitor. In the Workbench admin area
(`ai.theahg.co.za/admin` → Settings → Admin → **AHG Central**) an operator sees:

- stat cards - total / online / stale instances, and errors in the last 7 days;
- the site registry - each install's version, last-seen, online/stale status,
  heartbeat count and whether it auto-enrolled;
- recent errors across the whole fleet (redacted at source).

A no-dependency fallback dashboard is also available directly at
`central.theahg.co.za` (operator-token sign-in).

### Fleet notifications

AHG Central raises a notification in the Workbench bell for each fleet event:

- a **new instance enrolled** (first heartbeat from an unknown install);
- **critical errors** reported by an install (throttled per site);
- an **instance gone silent** (no heartbeat for over 25 hours).

## Settings reference (`Admin → AHG Settings → AHG Central`)

| Setting | Meaning |
|---|---|
| `ahg_central_enabled` | Master switch for all AHG Central traffic. Auto-on for a fresh install carrying the fleet key. |
| `ahg_central_error_sync` | Opt-in switch for error-log sync. Off by default. |
| `ahg_central_api_url` | AHG Central API base. Defaults to the deploy value. |
| `ahg_central_api_key` | The fleet enrolment key. Seeded from `.env`. |
| `ahg_central_site_id` | This install's identifier. Blank = auto-derived from the hostname. |

## Artisan commands

| Command | Purpose |
|---|---|
| `ahg:central-ping` | Check reachability of AHG Central. |
| `ahg:central-heartbeat` | Send the alive + version heartbeat (scheduled daily). |
| `ahg:central-sync-errors` | Push redacted error rows (scheduled hourly; no-ops unless both toggles are on). |

## Is AHG Central required?

No. AHG Central is advisory and best-effort. If it is unreachable, or disabled,
or the install is air-gapped, Heratio runs exactly as before - heartbeats fail
silently and are logged as warnings. Nothing in the product depends on it.
