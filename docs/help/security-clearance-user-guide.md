> Heratio Help Center article. Category: Security & Access Control.

# Security Clearance and Multi-Factor Authentication

Classifies archival items and users into hierarchical clearance levels, gates
sensitive material behind those levels plus optional compartments, and lets
users protect their accounts with multi-factor authentication using an
authenticator app, a passkey, or an emailed/texted one-time code. Administrators
manage clearances, review access requests, audit activity, and set a per-tenant
MFA enforcement policy.

---

## Overview

This module combines two related capabilities:

1. **Security clearance.** A hierarchy of classification levels (from Public up
   to Top Secret) that can be assigned to both users and archival descriptions.
   A user can see classified material only when their clearance level meets or
   exceeds the item's level, subject to optional compartment (need-to-know)
   restrictions. Users without sufficient clearance can submit an access
   request, which an administrator approves or denies. All activity is audited.

2. **Multi-factor authentication (MFA).** Three sibling second factors a user
   can enrol: a time-based one-time password (TOTP) authenticator app, a
   WebAuthn passkey or security key, and email/SMS one-time codes. Enrolment is
   opt-in per user, but an administrator can require MFA per tenant through an
   enforcement policy with a grace period.

The two are linked: a classification level can be flagged to require 2FA before
its material is viewed.

---

## Clearance levels

Classification levels live in the `security_classification` table. Each level
has a numeric rank, a code, a name, and a set of handling flags. The shipped
levels are:

| Level | Code | Name | Notes |
|---|---|---|---|
| 0 | `PUBLIC` | Public | Publicly accessible material |
| 1 | `INTERNAL` | Internal | Internal institutional use |
| 2 | `RESTRICTED` | Restricted | Limited staff; requires justification |
| 3 | `CONFIDENTIAL` | Confidential | Requires justification and approval; watermark required |
| 4 | `SECRET` | Secret | Highly sensitive; requires justification, approval, and 2FA; watermark required |
| 5 | `TOP_SECRET` | Top Secret | Highest level |

Each level carries handling flags that the platform enforces: whether it
requires justification, requires approval, requires 2FA, requires a watermark,
and whether download, print, and copy are allowed. Levels are configurable; the
table above lists the seeded defaults, and an administrator can add or adjust
levels.

These levels are jurisdiction-neutral and apply to any market. They are
ordinary configuration, not a fixed legal scheme.

### How clearance gates access

- A **user clearance** (`user_security_clearance`) records one active level per
  user, optionally with an expiry date. An expired clearance is treated as no
  clearance.
- An **object classification** (`object_security_classification`) records the
  level assigned to an archival description, with an optional reason and
  optional compartment assignments.
- Access is allowed when the user's clearance level is at least the item's
  level. Compartments add a need-to-know layer on top of the level check.
- When an item's level requires 2FA, the viewer must have cleared the MFA gate
  in their session.

---

## Compartments

Compartments (`security_compartment`) are named need-to-know groupings layered
on top of clearance levels. Each compartment has a code, a name, a minimum
clearance level, and flags for whether it requires need-to-know and a briefing.
A user must be granted access to a compartment (in addition to holding the
required level) to see material assigned to it. Administrators manage
compartments at `/admin/security-clearance/compartments` and review who has
which compartment at `/admin/security-clearance/compartment-access`.

---

## Key features

- Hierarchical, configurable clearance levels with per-level handling flags.
- Per-user clearance grant, update, revoke, and bulk grant, with expiry dates
  and a full change history.
- Per-object classification and declassification, with reasons and compartment
  assignment.
- Compartmented (need-to-know) access on top of levels.
- An access-request workflow for users who lack sufficient clearance.
- A security dashboard, reports, and a compliance view.
- A complete, filterable, exportable audit log.
- Watermark settings and a watermark-code trace tool.
- Three MFA factors (TOTP app, WebAuthn passkey, email/SMS OTP) with recovery
  codes for the authenticator-app factor.
- A per-tenant MFA enforcement policy with a grace period.

---

## How to use: security clearance

### Administrator: grant a clearance

1. Go to **Admin** and open **Security Clearance** at
   `/admin/security-clearance`. This lists every user with their current
   clearance and shows top-level statistics.
2. Open a user to view detail at `/admin/security-clearance/view/{id}`, or use
   the per-user-by-slug page at `/admin/security-clearance/user/{slug}`.
3. Choose a classification level, optionally set an expiry date, and add notes.
4. Submit. The grant is recorded and written to the clearance history.

The grant endpoint is `POST /admin/security-clearance/grant` (fields:
`user_id`, `classification_id`, optional `expires_at`, optional `notes`).
Submitting a classification of 0 revokes the clearance.

### Administrator: bulk grant

Use `POST /admin/security-clearance/bulk-grant` with a list of `user_ids` and a
single `classification_id` to grant the same level to many users at once.

### Administrator: revoke a clearance

Revoke from the user view or with `POST /admin/security-clearance/revoke/{id}`.
The revocation is logged with a reason.

### Administrator: classify or declassify an item

1. Open the classify page for an item at
   `/admin/security-clearance/classify/{id}`.
2. Choose a level, optionally give a reason, and optionally assign compartments.
3. Submit (`POST /admin/security-clearance/classify`). Any previous
   classification on the item is deactivated and the new one becomes active.

To declassify, open `/admin/security-clearance/declassification/{id}` and submit
`POST /admin/security-clearance/declassify`, optionally choosing a new (lower)
level. Both actions are written to the audit log.

### User: request access

A signed-in user who lacks sufficient clearance for an item can submit an access
request with `POST /security-clearance/access-request` (fields: `object_id`,
`request_type`, `justification`, optional `priority`, optional `duration_hours`,
default 24). They track their own requests and active grants at
`/security-clearance/my-requests`.

### Administrator: review access requests

1. Open **Access Requests** at `/admin/security-clearance/access-requests`.
   Filter by status (default Pending). The page shows counts for pending,
   approved today, denied today, and total this month.
2. Open one request at `/admin/security-clearance/access-requests/{id}`.
3. Approve (`POST .../{id}/approve`) with an optional note and a duration in
   hours (default 24), which sets a time-limited grant; or deny
   (`POST .../{id}/deny`) with an optional note.

### Dashboard, reports, and compliance

- **Dashboard** (`/admin/security-clearance/dashboard`): pending requests,
  clearances expiring within 30 days, due declassifications, and breakdowns by
  level.
- **Report** (`/admin/security-clearance/report`): clearance and request
  statistics over a selectable period (default 30 days).
- **Compliance** (`/admin/security-clearance/compliance`): classified-object
  and cleared-user counts plus recent compliance log entries.

### Audit log

- **Audit dashboard** (`/admin/security-clearance/audit/dashboard`): event
  counts by user, action, and day over a period.
- **Audit index** (`/admin/security-clearance/audit`): the full log, filterable
  by user, action, category, and date range, paginated 50 per page.
- **Export** (`/admin/security-clearance/audit/export`): downloads the log as
  CSV (date/time, user, action, category, object, IP address).
- **Object access audit** (`/admin/security-clearance/audit/object-access`):
  access history for one item.

### Watermarking

- **Watermark settings** (`/admin/security-clearance/watermark-settings`):
  toggle default watermarking, choose a default type, and control whether
  watermarks apply on view and on download, plus a security override and a
  minimum size. Settings are stored in the application setting tables.
- **Trace watermark** (`/admin/security-clearance/trace-watermark`): paste a
  watermark code to look it up in the access log and trace which access produced
  it.

---

## How to use: multi-factor authentication

A user can enrol any combination of the three factors. When more than one factor
is enrolled, the login-time prompt routes through a chooser; a single-factor
user goes straight to that factor's verify page. Any one enrolled factor
satisfies the MFA gate. After a successful verify, a session marker
(`security_2fa_session`) is created and is valid for 8 hours, so the user is not
re-prompted on every request.

### Authenticator app (TOTP)

1. Go to **Set up two-factor** at `/security-clearance/setup-2fa`. The page
   shows a QR code and a secret.
2. Scan the QR code with any TOTP authenticator app (the standard RFC 6238
   scheme), or type the secret in manually.
3. Enter the current 6-digit code to confirm
   (`POST /security-clearance/confirm-2fa`). On success the factor goes active.
4. You are shown a one-time set of **10 recovery codes**. Save them; they are
   not shown again. Each is single-use.

Recovery codes:

- View the recovery-codes page at `/security-clearance/recovery-codes` (codes
  are only shown immediately after generation; reloading later shows nothing).
- Regenerate a fresh batch of 10 at
  `/security-clearance/recovery-codes/regenerate`; the previous batch is
  invalidated.
- A low-count warning is flashed at login when 2 or fewer remain.

At login, enter a 6-digit code or a single-use recovery code at
`/security-clearance/verify-2fa`. The verifier allows a one-step time window for
clock drift.

Disable TOTP yourself at `/security-clearance/disable-2fa`; you must enter a
current code or recovery code to confirm. An administrator can clear a user's
2FA (when the user has lost both their app and all recovery codes) with
`POST /admin/security-clearance/remove-2fa/{id}`; the action is logged.

### Passkey / security key (WebAuthn)

1. Open the passkey management page at `/security/2fa/webauthn`.
2. Choose **Add** (`/security/2fa/webauthn/add`), give the credential a label
   (for example "Laptop Touch ID" or a hardware key model), and follow the
   browser prompt. The browser registers the credential against the site host.
3. The credential is stored and listed for management; you can enrol more than
   one and delete any of them (`POST /security/2fa/webauthn/{id}/delete`).

At login, the passkey challenge page (`/security/2fa/webauthn/verify`) triggers
the browser to assert the credential; on success the MFA gate clears. A
monotonic sign-count check guards against credential replay.

### Email or SMS one-time code (OTP)

1. Open the OTP management page at `/security/2fa/otp`.
2. Choose **Add** (`/security/2fa/otp/add`), pick **email** or **SMS**, enter
   the destination (a valid email address, or an international phone number such
   as +27821234567), and an optional label.
3. Enrol (`POST /security/2fa/otp/enrol`). A 6-digit code is sent to the
   destination. Enter it to confirm ownership; the factor is then verified and
   ready to use.
4. Manage your enrolled destinations (with masked display) and delete any of
   them from the list page.

At login, the OTP verify page (`/security/2fa/otp/verify`) lets you pick a
verified destination and have a code sent, then enter it to clear the gate.

OTP behaviour:

- Codes are 6 digits and expire after 10 minutes.
- At most one code per destination every 60 seconds (resend throttle).
- After 5 failed attempts within a 15-minute window, the destination is locked
  out for that window.
- Codes are never stored in plaintext (only a hash is kept).

---

## How to use: MFA enforcement policy (administrators)

By default MFA is optional and each user chooses whether to enrol. An
administrator can require it per tenant.

1. Open **MFA policy** at `/admin/security/mfa-policy`. This lists each tenant
   with its effective policy and shows the global default.
2. Edit a tenant's policy at `/admin/security/mfa-policy/{tenantId}/edit`, or
   edit the **global default** by using tenant ID 0.
3. Choose an **enforcement** level and a **grace period** in days (0 to 365),
   then save (`POST /admin/security/mfa-policy/{tenantId}`).
4. To make a tenant fall back to the global default, reset it
   (`POST /admin/security/mfa-policy/{tenantId}/reset`). The global default
   itself cannot be reset away.

Enforcement values (taxonomy `mfa_enforcement` in the Dropdown Manager):

| Code | Label | Effect |
|---|---|---|
| `off` | Off | Factor enrolment hidden; nothing required |
| `optional` | Optional | User choice; no enforcement (default) |
| `required_for_admins` | Required for admins | Admin and editor users must enrol |
| `required` | Required for everyone | Every authenticated user must enrol |

How enforcement is applied:

- The effective policy is resolved as: the tenant-specific row, else the global
  default row, else a built-in fallback of optional with a 7-day grace period.
- When a policy requires MFA and the user has no verified factor, the
  `EnforceMfaPolicy` middleware acts. Inside the grace window the user sees a
  yellow banner but is let through; once the window expires they are redirected
  to the enrolment page until they enrol.
- The grace clock runs from the later of the policy's last update or the user's
  account creation, so flipping a tenant to "required" gives existing users a
  fresh window.
- Enrolment, verification, and logout paths are always reachable so a user is
  never trapped in a redirect loop.

---

## Routes

### Clearance, requests, audit, watermark (admin)

| Method | URI | Action |
|---|---|---|
| GET | `/admin/security-clearance/dashboard` | dashboard |
| GET | `/admin/security-clearance` | index (users + clearances) |
| GET | `/admin/security-clearance/view/{id}` | view user clearance |
| POST | `/admin/security-clearance/grant` | grant/update clearance |
| POST | `/admin/security-clearance/revoke/{id}` | revoke clearance |
| POST | `/admin/security-clearance/bulk-grant` | bulk grant |
| POST | `/admin/security-clearance/revoke-access/{id}` | revoke object access grant |
| GET | `/admin/security-clearance/compartments` | compartments |
| GET | `/admin/security-clearance/compartment-access` | compartment access grants |
| GET | `/admin/security-clearance/classify/{id}` | classify form |
| POST | `/admin/security-clearance/classify` | apply classification |
| GET | `/admin/security-clearance/declassification/{id}` | declassify form |
| POST | `/admin/security-clearance/declassify` | declassify |
| GET | `/admin/security-clearance/report` | report |
| GET | `/admin/security-clearance/compliance` | compliance dashboard |
| GET/POST | `/admin/security-clearance/watermark-settings` | watermark settings |
| GET/POST | `/admin/security-clearance/trace-watermark` | trace watermark |
| GET/POST | `/admin/security-clearance/user/{slug}` | user clearance by slug |
| POST | `/admin/security-clearance/remove-2fa/{id}` | admin: remove user 2FA |
| GET | `/admin/security-clearance/access-requests` | access requests list |
| GET | `/admin/security-clearance/access-requests/{id}` | view request |
| POST | `/admin/security-clearance/access-requests/{id}/approve` | approve request |
| POST | `/admin/security-clearance/access-requests/{id}/deny` | deny request |
| GET | `/admin/security-clearance/audit/dashboard` | audit dashboard |
| GET | `/admin/security-clearance/audit` | audit index |
| GET | `/admin/security-clearance/audit/export` | export audit CSV |
| GET | `/admin/security-clearance/audit/object-access` | object access audit |

### MFA policy (admin)

| Method | URI | Action |
|---|---|---|
| GET | `/admin/security/mfa-policy` | list policies |
| GET | `/admin/security/mfa-policy/{tenantId}/edit` | edit (id 0 = global default) |
| POST | `/admin/security/mfa-policy/{tenantId}` | save |
| POST | `/admin/security/mfa-policy/{tenantId}/reset` | revert tenant to global default |

### User self-service (authenticated)

| Method | URI | Action |
|---|---|---|
| GET | `/security-clearance/my-requests` | my requests + grants |
| POST | `/security-clearance/access-request` | submit access request |
| GET | `/security-clearance/denied` | access-denied page |
| GET | `/security-clearance/two-factor` | login-time 2FA entry |
| GET | `/security-clearance/two-factor/choose` | chooser (multiple factors) |
| POST | `/security-clearance/verify-2fa` | verify TOTP / recovery code |
| GET | `/security-clearance/setup-2fa` | TOTP setup (QR) |
| POST | `/security-clearance/confirm-2fa` | confirm TOTP enrolment |
| POST | `/security-clearance/send-email-code` | send an email verification code |
| GET | `/security-clearance/recovery-codes` | show recovery codes (once) |
| POST | `/security-clearance/recovery-codes/regenerate` | regenerate recovery codes |
| GET/POST | `/security-clearance/disable-2fa` | disable TOTP (needs a code) |
| GET | `/security/2fa/webauthn` | passkey management |
| GET | `/security/2fa/webauthn/add` | add passkey |
| POST | `/security/2fa/webauthn/register/begin` | start registration |
| POST | `/security/2fa/webauthn/register/complete` | finish registration |
| POST | `/security/2fa/webauthn/{id}/delete` | delete passkey |
| GET | `/security/2fa/webauthn/verify` | login-time passkey challenge |
| POST | `/security/2fa/webauthn/assert/begin` | start assertion |
| POST | `/security/2fa/webauthn/assert/complete` | finish assertion |
| GET | `/security/2fa/otp` | OTP factor management |
| GET | `/security/2fa/otp/add` | add OTP destination |
| POST | `/security/2fa/otp/enrol` | enrol + send first code |
| GET/POST | `/security/2fa/otp/{factor}/verify-enrolment` | confirm ownership |
| POST | `/security/2fa/otp/{factor}/resend-enrolment` | resend enrolment code |
| POST | `/security/2fa/otp/{factor}/delete` | delete factor |
| GET | `/security/2fa/otp/factors.json` | verified factors (JSON) |
| GET | `/security/2fa/otp/verify` | login-time OTP entry |
| POST | `/security/2fa/otp/assert/begin` | send login code |
| POST | `/security/2fa/otp/assert/complete` | verify login code |

Several legacy `/security/*` URLs redirect to their `/admin/security-clearance/*`
equivalents for backward compatibility.

---

## Data model (selected tables)

| Table | Purpose |
|---|---|
| `security_classification` | Classification levels and handling flags |
| `user_security_clearance` | One active clearance per user, with optional expiry |
| `user_security_clearance_log` | Clearance change history |
| `object_security_classification` | Per-item classification |
| `security_compartment` | Need-to-know compartments |
| `security_access_request` | User access requests and their review outcome |
| `security_access_log` | Access grant/deny log |
| `security_audit_log` | General security audit events |
| `security_compliance_log` | Compliance events |
| `security_declassification_schedule` | Scheduled declassifications |
| `security_2fa_session` | Post-verify session marker (8-hour validity) |
| `user_totp_secret` | TOTP enrolment (secret, enabled-at, last-used) |
| `user_mfa_recovery_code` | Hashed single-use recovery codes |
| `ahg_webauthn_credential` | Enrolled passkeys / security keys |
| `ahg_otp_factor` | Enrolled email/SMS OTP destinations |
| `ahg_otp_challenge` | Short-lived hashed OTP codes |
| `ahg_mfa_policy` | Per-tenant (or global) MFA enforcement policy |

---

## Configuration

- **Classification levels and flags** are rows in `security_classification`;
  edit the level, code, name, and handling flags to fit your institution. The
  seeded six-level scheme above is a starting point, not a fixed requirement.
- **MFA enforcement vocabulary** lives in the Dropdown Manager
  (`/admin/dropdowns`) under taxonomy `mfa_enforcement`. The four values are
  seeded on first boot.
- **MFA policy defaults** to optional with a 7-day grace period; change it per
  tenant or globally from the MFA policy admin page.
- **Schema** for the MFA factors and the policy table installs automatically on
  first boot, idempotently. The service provider also back-fills missing TOTP
  columns on older installs and registers the `mfa.policy` middleware alias so
  the enforcement gate can be attached to the web stack.
- **WebAuthn relying-party ID** is the request host (no scheme, no port), so a
  self-hosted instance behind a reverse proxy must be reached at a stable
  hostname for passkeys to work.
- All AI and email transports use the application's standard configuration; this
  module adds no external service endpoints of its own.

---

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---|---|---|
| User cannot see an item they expect | Clearance level below the item, expired clearance, or missing compartment | Check the user's clearance and the item's classification and compartments |
| User locked out of OTP | 5 failed attempts in 15 minutes | Wait out the 15-minute window, then resend |
| OTP code never arrives | Send throttle (1 per 60s) or transport failure | Wait 60 seconds and resend; verify mail/SMS transport |
| Passkey will not register or assert | Site reached at a different hostname than enrolled | Use the same hostname for enrolment and login |
| User lost authenticator and recovery codes | No second factor available | An administrator clears 2FA with the admin remove-2FA action |
| Everyone is forced to enrol unexpectedly | Tenant or global policy set to required | Review the MFA policy admin page |

---

## References

- **Source package:** `packages/ahg-security-clearance/`
- **Controllers:** `src/Controllers/SecurityClearanceController.php`,
  `src/Controllers/OtpController.php`, `src/Controllers/WebAuthnController.php`,
  `src/Controllers/MfaPolicyController.php`
- **Services:** `src/Services/SecurityClearanceService.php`,
  `src/Services/TotpService.php`, `src/Services/WebAuthnService.php`,
  `src/Services/OtpService.php`, `src/Services/MfaPolicyService.php`
- **Middleware:** `src/Http/Middleware/EnforceMfaPolicy.php`
- **Schema:** `database/install.sql`
- **Routes:** `routes/web.php`
- **GitHub issue:** [#624](https://github.com/ArchiveHeritageGroup/heratio/issues/624);
  MFA factors and policy were delivered under Heratio #690 (TOTP), #721
  (WebAuthn), #722 (OTP), and #723 (enforcement policy).
