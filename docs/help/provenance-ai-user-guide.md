> Heratio Help Center article. Category: Transparency & Audit.

# AI Inference Provenance

## A Guide for Archivists, Reviewers, and Auditors

Heratio records the provenance of every piece of metadata produced by an automated/AI inference process. For each AI suggestion it captures which process produced it, when, against which descriptive standard, with what confidence, and whether a human later reviewed or corrected it. Given any record, you can pull a single, defensible trace of every AI decision that touched it.

---

## Overview

When an automated process suggests a value (for example a name access point, a transcription, or a translation), Heratio writes a permanent inference record before the value lands on the entity. The original AI suggestion is never overwritten. When a reviewer changes an AI-suggested value, that change is captured as a separate override record linked back to the inference, so the full history is preserved.

Two stores work together:
- The operational store powers fast filtering, dashboards, and review queues.
- A semantic store holds the canonical, audit-friendly record (a write that is retried automatically if the semantic store is briefly unavailable, so an inference is never lost).

This makes AI-assisted cataloguing transparent and accountable: you can always answer "where did this value come from, and who signed off on it?"

---

## Key features

| Feature | What it does |
|---------|--------------|
| **Inference recording** | One permanent record per AI suggestion: process type, model identity and version, input/output hashes, confidence, declared standard, target entity and field, and elapsed time. |
| **Override-as-event** | Reviewer corrections are recorded as new linked events, never as edits to the original. The before and after values, the reviewer, and an optional reason are all kept. |
| **One-query trace** | A single read-only endpoint returns the complete inference and override chain for any record, grouped by field, including the value currently in effect. |
| **Coverage diagnostic** | A summary of how many inferences each process produced over a chosen window, with average confidence. |
| **Governance dashboard** | Operator view of configured models and recent inference activity. |
| **Confidence-based review** | Low-confidence inferences can be auto-queued for human review when a threshold is configured. |
| **Optional signing** | Inference records can be cryptographically signed so their integrity can be verified later. |

---

## How to use

### Viewing the Governance dashboard (operators and admins)

1. Sign in with an account that has admin access.
2. Go to **Admin -> Governance**, or open `/admin/governance` directly.
3. The dashboard shows headline stat cards (total and active model configurations, total inferences, inferences in the last 7 days, and average confidence).
4. The **LLM Configurations** table lists each configured model with its provider, name, token and temperature settings, inference count, and last-used time. Secrets such as API keys are never displayed.
5. The **Recent Inferences** table shows the 50 most recent inferences with their process, model, target record, field, confidence, elapsed time, and signing status.

The dashboard is read-only. It is a summary view; the full per-record detail lives behind the trace endpoint below.

### Tracing every AI decision on a record

Any signed-in user can pull the complete provenance trace for an entity:

1. Note the entity type and its numeric id. Supported types are: `information_object`, `actor`, `repository`, `term`, and `museum_metadata`.
2. Request `GET /api/v1/provenance/{entityType}/{id}/trace`.
   - Example: `/api/v1/provenance/information_object/12345/trace`
3. The response groups results by field. For each field you see:
   - The inference (process, model, version, confidence, declared standard, input and output hashes, excerpts, endpoint, and time).
   - Any overrides (reviewer, reason, original value, new value, status, and time).
   - The value currently in effect for that field.
4. A summary block reports how many inferences and overrides exist and how many fields were touched.

This is the defensible shape an auditor needs: given a record id, every AI decision that touched it, by which process, at what confidence, against which standard, and what human corrections were applied.

### Checking process coverage

To confirm a process actually ran recently:

1. Request `GET /api/v1/provenance/coverage?days=7` (any window from 1 to 365 days).
2. The response lists, per process, the count of inferences, the average confidence, and how many records are still awaiting their semantic-store write.

### How overrides are recorded

You do not record overrides manually. When you edit an entity field that was previously suggested by an AI process and save your change, Heratio detects that the field had an inference and writes an override event automatically, capturing the before and after values and your user identity. Saving the same change twice within a short window is de-duplicated, so a double-click does not create duplicate records. An optional reason can be attached to explain the correction.

---

## Configuration

All settings are optional. Where a setting is unset, the related behaviour simply does not run, and AI writes are never blocked.

| Setting | Where | Effect |
|---------|-------|--------|
| `ai_provenance.<process>.confidence_review_threshold` | Settings store | Inferences from that process below the threshold are queued for human review. Process name is lower-cased (for example the threshold key for a name-extraction process). No setting means no auto-review. |
| `ai_provenance.review_workflow_id` and `ai_provenance.review_step_id` | Settings store | The workflow and step a low-confidence inference is queued into. If either is unset, the inference is logged and skipped rather than queued. |
| `fuseki_sync_enabled` | Settings store | Master toggle for the semantic-store write and its background replay. Turning it off silences the replay loop without any cache rebuild. |
| Linked-data tenant and namespace | `config/heratio.php` | Tenant prefix and provenance namespace used to build the semantic-store identifiers. |

### Maintenance commands

- **Generate the signing key (once per install):**
  `sudo -u www-data php artisan ahg:provenance-ai:keygen`
  Creates the signing keypair under `storage/`. The private key is never stored in the database or in version control. Until this is run, inferences are written unsigned, which is harmless. Use `--force` to replace an existing key.
- **Replay deferred semantic-store writes:**
  `php artisan ahg:provenance-ai:replay`
  Retries any inference or override whose semantic-store write was deferred. This runs automatically every five minutes when the sync toggle is enabled, and is a safe no-op when nothing is pending. Add `--dry-run` to count pending rows without writing, or `--batch=N` to cap rows per run.

The database tables are created automatically on first boot, so a fresh checkout needs no manual import step.

---

## Troubleshooting

| Problem | Likely cause |
|---------|--------------|
| Dashboard shows "unsigned" on every row | The signing keypair has not been generated yet. Run the keygen command. |
| A field has no trace | No AI process has touched that field; the value was entered manually. |
| Trace request returns an error | The entity type is not one of the supported types listed above. |
| Records show as awaiting their semantic-store write | The semantic store was briefly unavailable; the replay command catches them up automatically. |

---

## References

- Source package: `packages/ahg-provenance-ai/`
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/612

*For assistance, contact your Heratio administrator.*
