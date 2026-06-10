# Conservation alert escalation (#1188)

When a sensor reading breaches a conservation threshold, the breach is now **escalated to staff**
(the admin notification bell), not just listed in-app - completing the "fire alerts" half of the
live IoT sensor binding.

## How it works (ahg-exhibition)

- `ExhibitionSpaceService::ingestSensor()` records each reading and, on a threshold breach,
  inserts an `ahg_exhibition_alert` row (as before) then calls `escalateAlert()`.
- `escalateAlert($space, $metric, $breach, $alertId)`:
  - **Throttle** - skips if this space+metric was already escalated within the window
    (default 60 min; override via the `conservation_alert_throttle_min` setting), using the new
    `ahg_exhibition_alert.notified_at` column. Stops a sensor breaching every reading from
    spamming the bell.
  - Notifies all active admins via `AhgCore\Services\NotificationService::notifyAdmins()`
    (type `conservation`, links to the space analytics page), then stamps `notified_at`.
  - Best-effort: any failure is logged and never breaks sensor ingest.

## Verified

A `temp_c=35` reading on a space fired an alert + notified all 9 admins and stamped
`notified_at`; an immediate second breach was correctly throttled (0 new notifications).

## Thresholds (existing)

`temp_c` 16-24 C (critical <10/>28), `humidity` 40-60% RH (critical <30/>70), `lux` per the
space's lux target. Defaults in `conservationThreshold()`.

## Follow-ups

- Per-object/case sensor binding (today alerts are per-room).
- AI-recommended interventions on a breach.
- Optional external escalation (email / the workbench notification spool) alongside the bell.
