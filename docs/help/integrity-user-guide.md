> Heratio Help Center article. Category: Plugin Reference.

# Integrity Assurance - User Guide

## Prove Your Digital Files Are Intact, and Manage Their Whole Lifecycle

The Integrity module is the trust layer for your digital holdings. It verifies
that files have not changed or gone missing (fixity), tracks repeated failures so
they cannot be lost, and supports the full records lifecycle: retention policies,
legal holds, record declarations, vital-record reviews, disposition, and signed
destruction certificates.

It is built to be honest and durable. Verification results are written to an
append-only ledger that is never edited or deleted, so the verification history
survives even if a file is later removed.

---

## Overview

Integrity works from a central dashboard and a set of focused screens. At its
heart is fixity verification: the module computes a checksum for each digital
file and compares it against a stored baseline. Each check records an outcome -
pass, mismatch, missing, unreadable, no baseline, or error - in the ledger.

Verification runs can be scheduled (for example a daily sample check and a weekly
full scan) or triggered on demand. Files that keep failing are moved to a dead
letter queue so they are not forgotten, and threshold-based alerts can email an
address or call a webhook when things go wrong.

Around fixity sits a complete lifecycle toolkit: retention policies that flag
records as eligible for disposal, legal holds that block disposal, record
declarations, vital-record tracking with review cycles, a disposition queue, and
tamper-evident destruction certificates.

---

## Key features

- **Fixity verification** using configurable checksum algorithms (SHA-256 by
  default; SHA-512 and any other algorithm supported by the host are available).
  Comparisons are timing-safe.
- **Append-only ledger** - every verification outcome is recorded and never
  altered, preserving history even after a file is deleted.
- **Scheduled and on-demand runs** with batch size, runtime, memory, and throttle
  controls.
- **Dead letter queue** - objects that fail repeatedly are escalated after a
  configurable number of consecutive failures (default three).
- **Alerts** by email and webhook on failure or mismatch.
- **Retention policies** that scan for records past their retention period and
  queue them for disposition.
- **Legal holds** that block disposal, with full place and release history.
- **Record declarations** with an approval step.
- **Vital records** flagged for periodic review, with overdue tracking.
- **Destruction certificates** with a content hash for tamper evidence.

---

## How to use

All Integrity screens live under the **Integrity** area
(**`/integrity`**) and require an administrator login. The older
**`/admin/integrity`** address redirects here.

### Dashboard

Open **`/integrity`** for the overview: total objects, total verifications, pass
rate, open dead letters, never-verified count, recent throughput, a per-repository
breakdown, a failure-type breakdown, a 30-day trend, and the most recent runs.

### Verify fixity

- Review verification runs at **`/integrity/runs`**, and open a run for its
  per-object detail and failures.
- Inspect the full ledger at **`/integrity/ledger`**.
- Manage scan timings at **`/integrity/schedules`**; edit a schedule to change its
  name, cron expression, and active state.

### Handle failures

- See repeated failures at **`/integrity/dead-letter`**.
- See alerts at **`/integrity/alerts`**.

### Manage the records lifecycle

- **Retention policies:** **`/integrity/policies`** - edit a policy's name,
  description, frequency, and active state.
- **Disposition queue:** **`/integrity/disposition`** - records eligible for
  destruction.
- **Legal holds:** **`/integrity/holds`** - place a hold (giving the record and a
  reason), release a hold (with a reason), and view each record's hold history.
- **Record declarations:** **`/integrity/declarations`** - declare a record, then
  approve the declaration.
- **Vital records:** **`/integrity/vital-records`** - flag a record as vital with
  a review cycle, review it on schedule, and unflag it; overdue reviews are listed
  at **`/integrity/vital-records/overdue`**.
- **Retention events:** **`/integrity/retention-events`** - record an event that
  can trigger a retention rule.
- **Destruction certificates:** **`/integrity/certificates`** - generate a
  certificate for a queued disposition (recording who authorised it, the method,
  and any witness), then view the saved certificate. Each certificate carries a
  content hash so any later tampering is detectable.

---

## Configuration

Integrity is controlled by a set of settings (the `integrity_*` group). The key
ones:

| Setting | Purpose | Default |
|---------|---------|---------|
| Enabled | Master switch for scheduled scans (on-demand checks still work when off) | On |
| Default algorithm | Checksum algorithm for new checks | SHA-256 |
| Default batch size | Objects processed per batch | 200 |
| Max runtime | Wall-clock cap per scan, in seconds | 120 |
| Max memory | Memory cap per scan, in MB | 512 |
| Throttle | Pause between hashes, in milliseconds | 10 |
| Auto baseline | Create a baseline checksum on first scan | On |
| Dead letter threshold | Consecutive failures before escalation | 3 |
| Alert email | Address for failure and mismatch alerts | empty (off) |
| Webhook URL | Endpoint for failure and mismatch alerts | empty (off) |
| Notify on failure / on mismatch | Whether to raise each alert type | On |

A baseline checksum is the trusted reference a file is checked against. With
auto-baseline on, the first scan establishes the baseline; later scans compare
against it.

---

## Notes

- Fixity outcomes are: pass, mismatch (the hash differs), missing (the file is
  gone), unreadable or permission error, path drift, no baseline, or error.
- The ledger is append-only by design. It is never updated or deleted, so a
  record's verification history is preserved even after the file itself is
  removed.
- Default schedules ship as a daily sample check and a weekly full scan; adjust or
  add schedules to suit your collection size.

---

## References

- Source package: `packages/ahg-integrity/`
- GitHub issue: [GH #586](https://github.com/ArchiveHeritageGroup/heratio/issues/586)
