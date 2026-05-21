> Heratio Help Center article. Category: User Manual.

# Time-Limited Share Link — User Manual

**Plugin:** `ahgTimeLimitedShareLinkPlugin` (AtoM) / `ahg-share-link` (Heratio)
**Version:** 0.1.0
**Audience:** Archive administrators, editors, and contributors

---

## 1. Who can do what

| Role                | Issue a link | Issue for classified records | Exceed the 90-day cap | View all links | Revoke other users' links |
|---------------------|:------------:|:----------------------------:|:---------------------:|:--------------:|:------------------------:|
| Administrator       | yes          | yes                          | yes                   | yes            | yes                      |
| Editor (group 101)  | yes          | no                           | no                    | yes            | yes                      |
| Contributor (group 102) | yes      | no                           | no                    | no             | own only                 |
| Translator (group 103) | no        | no                           | no                    | no             | own only                 |

The administrator group (100) bypasses every check in code; the other roles get explicit grants seeded at install time (see the `share_link.*` rows in `acl_permission`).

To grant a non-default permission such as `share_link.create_classified` to a specific user or group, an administrator opens **Admin > Users (or Groups) > Permissions** and adds the action to that grant scope.

---

## 2. Issuing a share link

### Step 1 — Open the record

Navigate to any information object's view page. You'll see a **"Share this record"** button at the top of the record's content area, next to the version-history banner if `ahgVersionControlPlugin` is also enabled.

> The button only appears if you have `share_link.create`. If you can't see it, ask an administrator to grant the permission to your role.

### Step 2 — Open the modal

Clicking **"Share this record"** opens a modal with four fields:

| Field            | Required | Notes |
|------------------|:--------:|-------|
| Expires on       | yes      | Defaults to 14 days from today; capped at 90 days unless your account has `share_link.create_unlimited_expiry`. |
| Recipient email  | no       | A hint for the audit trail; not used to actually send email. The recipient still just gets the URL. |
| Note for recipient | no     | Free text stored alongside the token. Visible to admins on the per-link detail page. |
| Max visits       | no       | Optional cap on how many times the link can be opened. Leave blank for unlimited within the expiry window. |

### Step 3 — Generate

Click **"Create share link"**. On success the modal shows the public URL with a **Copy** button:

```
https://psis.theahg.co.za/share/abc1234567890123456789012345678901234567890
```

Share that URL via whatever channel you trust (email, secure messaging, signed letter). Once expired or revoked the URL returns a friendly **"This share link is no longer valid"** page; the record is never publicly leaked.

> The URL is the credential. Anyone who has the URL can open the record — that's the point. Treat it like a temporary password.

### What happens if a guard fails

| Error                                  | What it means                                                                     |
|----------------------------------------|-----------------------------------------------------------------------------------|
| **You do not have permission to issue share links** | Your role lacks `share_link.create`. Ask an admin.                  |
| **You do not have permission to issue share links for classified records** | The record is classified and you lack `share_link.create_classified`. |
| **Insufficient clearance**             | The record is classified above your clearance level under `ahgSecurityClearancePlugin`. |
| **Expiry is capped at 90 days**        | Your account doesn't have `share_link.create_unlimited_expiry`.                   |
| **expires_at must be in the future**   | You picked today or earlier. Pick a future date.                                  |
| **recipient_email is not a valid email address** | Fix the typo or leave the field blank.                                  |

---

## 3. Revoking a link

Two ways to revoke:

### From the admin list

1. Open **Admin > Share links** (`/admin/share-links`).
2. Find the row you want to revoke.
3. Click **Revoke**. A confirm prompt asks you to acknowledge that recipients will no longer be able to view the record.

### From the per-link detail page

1. Open **Admin > Share links**.
2. Click **View** on the relevant row.
3. Click **Revoke** in the header.

Revocation is immediate. The very next click on that URL returns a 410 page with a "revoked" reason. Subsequent revoke attempts on the same link are silently treated as no-ops (you get an informational "This share link was already revoked" notice).

> Editors can revoke any link via `share_link.revoke_others`. Contributors can only revoke their own.

---

## 4. The admin list — what each column means

| Column     | Meaning |
|------------|---------|
| Status     | **Active** (currently usable) · **Expired** (past `expires_at`) · **Revoked** (admin-revoked) · **Exhausted** (visit quota reached). |
| Record     | The information-object title (current culture, first available fall-back) plus the numeric ID. |
| Issuer     | The user who created the link. |
| Recipient  | The recipient hint email, or `—` if not supplied. |
| Issued     | Token creation timestamp. |
| Expires    | The token's `expires_at`. |
| Visits     | Used / max — e.g. `3 / 5`. Shows just the count if no quota. |
| Token      | First 12 characters of the URL token. Full token visible on the detail page. |
| Actions    | **View** opens the detail page · **Revoke** revokes the link. |

### Filters

- **Status** — drop-down: Active (default) · Expired · Revoked · Exhausted · All.
- **Issuer** — drop-down of users who have issued at least one link.
- **Search** — free-text match against token, recipient email, or record title.

Pagination is 25 rows per page.

---

## 5. The per-link detail page

Shows everything captured at issuance time plus the access log:

- **Record** — title and ID.
- **Issuer** — username and email.
- **Recipient** — email and free-text note.
- **Issued / Expires / Visits / Revoked at** — lifecycle dates.
- **Classification level at issuance** — captured value, so subsequent re-classification doesn't quietly broaden the link's scope.
- **Public URL** — the exact URL recipients have.
- **Access log (last 200)** — every hit. Columns: when, outcome (Viewed / Denied — expired / Denied — revoked / Denied — quota exhausted), IP, user agent.

---

## 6. Settings (admin only)

Find the share-link settings under **Admin > AHG Settings > Share Links** (AtoM) or `/admin/settings/ahg/share_link` (Heratio).

| Setting                              | Default | What it controls |
|--------------------------------------|---------|------------------|
| `share_link.default_expiry_days`     | 14      | Pre-filled in the Share modal when an issuer doesn't supply an expiry. |
| `share_link.max_expiry_days`         | 90      | Hard cap. Issuers without `share_link.create_unlimited_expiry` are capped here. |
| `share_link.token_retain_days`       | 365     | Retention sweep deletes tokens whose `expires_at` OR `revoked_at` is older than this. |
| `share_link.access_log_retain_days`  | 180     | Retention sweep trims access-log rows older than this regardless of parent token state. |
| `share_link.hmac_secret`             | auto-generated | The per-install HMAC secret used to derive tokens. Auto-bootstrapped on first issue. Rotation is intentionally not exposed in the UI. |

Setting changes take effect immediately. The next prune sweep picks up the new retention values; the next issuance picks up the new defaults.

---

## 7. Retention sweeps (admins / operations)

A daily cron job applies the two retention values:

### AtoM (PSIS)

```bash
15 3 * * *   cd /usr/share/nginx/archive && php symfony share-link:prune >> /var/log/atom/share-link-prune.log 2>&1
```

### Heratio

```bash
15 3 * * *   cd /usr/share/nginx/heratio && php artisan share-link:prune >> /var/log/heratio/share-link-prune.log 2>&1
```

Add `--dry-run` to either command to preview what would be deleted without changing anything. Both commands are idempotent — a no-op run is safe and writes no audit row.

Each non-empty run writes a `share_link_prune` row to `ahg_audit_log` with the counts and the retention values used.

> The pre-canned cron entry is also listed in **Admin > AHG Settings > Cron Jobs** with the category "audit" alongside other audit-housekeeping crons.

---

## 8. What recipients see

When the recipient opens the URL:

| Token state                                       | What they see                                              | HTTP |
|---------------------------------------------------|-------------------------------------------------------------|------|
| Valid, not revoked, not expired, quota not reached | The record's title, scope and content, identifier; banner "Shared by *issuer* · expires *date*" | 200  |
| Expired                                           | A clean "This share link has expired" page                  | 410  |
| Revoked                                           | A clean "This share link has been revoked" page             | 410  |
| Quota exhausted                                   | A clean "This share link has reached its maximum access count" page | 410  |
| Bogus / unknown token                             | A clean "Share link not found" page                         | 410  |

All four denial pages carry `Referrer-Policy: no-referrer` so the token isn't leaked in a referer header when the recipient navigates onward.

---

## 9. Troubleshooting

| Symptom                                     | Likely cause                                                                            |
|---------------------------------------------|-----------------------------------------------------------------------------------------|
| **"Share this record" button doesn't appear** | The plugin isn't enabled, or you lack `share_link.create`. Confirm in Admin > AHG Plugins. |
| **Issue modal opens but Save returns 401** | Session expired. Re-login and try again.                                                |
| **Issue returns "Expiry is capped at 90 days"** | You picked a date past the configured `max_expiry_days`. Pick a closer date, or ask for `share_link.create_unlimited_expiry`. |
| **Recipient gets "Share link not found"** | URL was mistyped, copied with a trailing punctuation mark, or the token was already pruned. |
| **All my old tokens disappeared overnight** | The retention sweep ran and removed expired tokens past `token_retain_days`. Lengthen the setting if you need longer retention. |
| **HMAC rotation needed** | Out of scope of the v1 admin UI. Coordinate with the dev team; rotation invalidates every existing token. |

For unresolved problems, check `ahg_audit_log` (filter `module=share_link`) and the AtoM error-log page (`/ahgSettings/errorLog`).

---

## 10. Glossary

- **Token** — the unguessable 43-character URL-safe string that uniquely identifies a share link. HMAC-SHA256 derived; stored verbatim in `information_object_share_token.token`.
- **Bearer authentication** — the recipient doesn't log in. The token IS the credential. Whoever holds it gets the access the token permits.
- **HMAC secret** — a 64-character hex string stored in `ahg_settings.share_link.hmac_secret`. Used as the HMAC key when generating tokens. Auto-generated on first use; rotation invalidates every token.
- **Quota** — the optional `max_access` cap on a single token. Once the visit count meets the quota every subsequent hit returns `denied_quota`.
- **Retention** — the `_retain_days` settings. The prune sweep deletes tokens / access rows older than these thresholds.

---

© 2026 The Archive and Heritage Group (Pty) Ltd. AGPL-3.0-or-later.
