> Heratio Help Center article. Category: Reference.

# Time-Limited Share Link — Feature Overview

**Plugin:** `ahgTimeLimitedShareLinkPlugin` (AtoM) / `ahg-share-link` (Heratio)
**Version:** 0.1.0 (initial release)
**Author:** The Archive and Heritage Group (Pty) Ltd
**Category:** Access control / public engagement

## What it does

Lets authorised staff send a recipient a private, time-limited URL to a single archival record. Anyone holding the link sees the record's title and description until the link expires or is revoked. Every issue, view, denial, and revocation is audited end-to-end.

Designed for archives that need to share a record with a researcher, journalist, agency, or external reviewer **without** creating a permanent user account or making the record publicly browseable.

## Key features

- **Time-limited, single-record URLs.** Each link points to exactly one information object. Defaults to 14 days; capped at 90 days unless the issuer holds the `share_link.create_unlimited_expiry` permission. Both defaults are admin-configurable.
- **HMAC-derived, unguessable tokens.** 43-character URL-safe tokens generated with HMAC-SHA256 over a per-install secret. The secret is auto-bootstrapped on first use; rotation invalidates every existing token.
- **Optional visit quota.** Issuers can cap the number of times a link can be opened. Once the quota is exhausted the URL returns a friendly "denied" page; the access is still recorded.
- **Recipient hint.** Issuers can attach a recipient email and free-text note. Both are stored alongside the token for audit and to help admins recognise the link in the management UI.
- **Anonymous bearer access.** No login required on the recipient side — the token is the credential. The recipient lands on a clean record view branded "Shared by *issuer* · expires *date*" with a `Referrer-Policy: no-referrer` header to prevent token leakage via the Referer header.
- **Classified-record guard.** Issuing a link for a classified record requires the `share_link.create_classified` permission **and** clearance at or above the record's current classification level, enforced via `ahgSecurityClearancePlugin`.
- **Admin index + per-token detail.** `/admin/share-links` lists every link with status (active / expired / revoked / exhausted), issuer, recipient, expiry, visit count, and revoke button. Per-token detail page shows the full access log (last 200 hits — IP, user agent, outcome).
- **One-click revocation.** Revoking a link sets `revoked_at` immediately. The very next click on the link returns a 410 with a "revoked" reason. Owners can revoke their own links; revoking another user's link requires the `share_link.revoke_others` permission.
- **Retention sweeps.** A daily cron command (`php symfony share-link:prune` / `php artisan share-link:prune`) deletes tokens whose expiry or revocation date is older than the configured retention, and trims access-log rows older than the access-log retention. Defaults: 365 days for tokens, 180 days for access rows.
- **End-to-end audit trail.** Issue, every access attempt (allowed and denied), every revoke, and every retention sweep are mirrored into `ahg_audit_log` with module=`share_link`. Compliance reviews see share-link events alongside every other auditable action.
- **Cross-surface parity.** Identical schema, token format, audit payload, and admin UX across AtoM (Symfony 1.x) and Heratio (Laravel). A token issued on one surface can be accessed on the other.

## Compliance and standards alignment

| Standard / regulation | How this plugin supports it |
|---|---|
| **POPIA / GDPR / CCPA** | All access is auditable; tokens expire automatically; revocation is immediate; recipient context (email + note) is stored for accountability |
| **NARSSA** records integrity | All four lifecycle events (issue / access / revoke / prune) are dual-written to the central audit trail |
| **GCIS RFB 001 2026/2027** clauses 4.1.1.9 (audit trail management) and 4.4 (security & access) | Granular ACL (5 distinct permissions) + per-token audit trail |
| **ISO 27001** access control | Bearer-token credentials are time-limited, single-record-scoped, individually revokable, and fully audited |
| **MISS classification** | Classified records require explicit permission + matching clearance at issuance time; classification level is captured at issuance so subsequent re-classification doesn't quietly broaden the link's scope |

## How it scales

- **Token storage** ~250 bytes per token row. 10,000 active + retained tokens ≈ 2.5 MB.
- **Access-log storage** ~400 bytes per access row. A heavily-shared archive at 1,000 hits/day with 180-day retention ≈ 70 MB.
- **Read path** is a single indexed lookup on `information_object_share_token.token` (UNIQUE) plus a SELECT for the i18n record.
- **No background daemons** required. The retention sweep is a daily cron; it's a no-op when no rows qualify.

## Permission matrix (default)

| Permission                              | Admin (100) | Editor (101) | Contributor (102) | Translator (103) |
|------------------------------------------|:-----------:|:------------:|:-----------------:|:----------------:|
| `share_link.create`                      | bypass | ✓ | ✓ | — |
| `share_link.create_classified`           | bypass | — | — | — |
| `share_link.create_unlimited_expiry`     | bypass | — | — | — |
| `share_link.list_all`                    | bypass | ✓ | — | — |
| `share_link.revoke_others`               | bypass | ✓ | — | — |

Administrators bypass all checks in code. Default seeds are idempotent and additive — re-running them never duplicates or overwrites existing grants.

## What's audited

Every operation writes a row to `ahg_audit_log` (module = `share_link`):

| Action                  | Trigger                              | Status flag | Metadata captured |
|-------------------------|--------------------------------------|-------------|-------------------|
| `share_link_issued`     | Successful token issuance            | success     | token_id, expires_at, recipient_email, recipient_note, max_access, classification_level |
| `share_link_accessed`   | Every recipient hit (allowed or denied) | success / failure | action_name: view / denied_expired / denied_revoked / denied_quota / denied_unknown |
| `share_link_revoked`    | Token revoke                         | success     | token_id, was_owner flag, optional reason |
| `share_link_prune`      | Retention sweep with deletions       | success     | tokens_deleted, access_rows_deleted, retention values used |

## Technical requirements

- **AtoM** 2.10 (Symfony 1.x), MySQL 8, PHP 8.1+
- **Heratio** (Laravel 10+), MySQL 8, PHP 8.2+
- **Required plugin:** `ahgCorePlugin`
- **Recommended plugins (graceful fall-back if missing):**
  - `ahgAuditTrailPlugin` — for the central audit feed
  - `ahgSecurityClearancePlugin` — for classified-record guards
  - `ahgSettingsPlugin` — for the integrated settings UI; if absent the plugin still reads defaults and runs

## Out of scope (current release)

- Bulk issuance (one-by-one only). A "Generate links for everything in this folder" flow is a future enhancement.
- Recipient self-service download for digital objects beyond what the issuer can download themselves at issuance time. The `issuer_download_at_issuance` flag is captured for use in a forthcoming digital-object download flow.
- HMAC secret rotation runbook. The secret is auto-generated on first use; rotation invalidates every existing token and is intentionally **not** exposed in the admin UI to prevent accidental link-mass-invalidation.

## Licence

AGPL-3.0-or-later. © 2026 The Archive and Heritage Group (Pty) Ltd.
