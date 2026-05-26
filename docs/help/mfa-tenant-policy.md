# MFA Tenant Enforcement Policy

> User-facing help for administrators managing the per-tenant multi-factor authentication policy at `/admin/security/mfa-policy`. Shipped in v1.90+ for issue #723. Sits on top of the opt-in TOTP / WebAuthn / Email-SMS OTP factors from issues #690 / #721 / #722.

## Overview

The MFA Enforcement Policy lets a tenant administrator decide whether the multi-factor authentication is **optional**, **required for admins only**, or **required for every user** signed in to that tenant. Each tenant can set its own policy; tenants without an explicit row inherit the **global default**.

Users who do not yet have any verified factor (authenticator app, passkey, or email/SMS code) and who fall under a "required" policy are redirected to the enrolment page on their next request after their **grace period** expires. While the grace period is still active, the user sees a yellow banner reminding them to enrol but can continue working.

## How to access

1. Sign in as an administrator.
2. Open **Admin -> Security -> MFA Enforcement** (`/admin/security/mfa-policy`).

You will see:

- A **Global default** card at the top.
- A table of every tenant with its current effective policy.
- An **Edit** button per row.
- A **Reset to global** button on tenant-specific rows.

## Enforcement values

| Value | Effect |
| --- | --- |
| `off` | Factor enrolment surfaces are disabled. Users cannot enrol new factors. Use this only for incident response. |
| `optional` | Default. Users may enrol on their own; nothing is forced. |
| `required_for_admins` | Users in the Administrator or Editor group must enrol. Other users are not affected. |
| `required` | Every signed-in user under this tenant must enrol. |

## Grace period

The grace period (in days, 0 to 365) is the window during which a user under a "required" policy can still defer enrolment. The middleware shows a yellow banner instead of redirecting. After the window, the next request is redirected to `/security-clearance/setup-2fa`.

The clock starts from the most recent of:

- the policy row's `updated_at` (so flipping from `optional` to `required` resets the clock for everyone), and
- the user's `created_at` (so brand-new accounts still get a full window even after a policy flip).

Set the grace period to `0` to enforce immediately on the next request.

## Editing a policy

1. From the index page, click **Edit** beside the tenant (or beside **Global default**).
2. Choose the new enforcement value.
3. Set the grace period.
4. Click **Save policy**.

The change is effective on the very next request from every user under that tenant.

## Resetting to the global default

Click **Reset to global** on any tenant-specific row. This deletes the tenant's row from `ahg_mfa_policy` so the tenant falls back to the global default policy. The global default itself cannot be reset to itself.

## See also

- [TOTP setup user guide](./two-factor-authentication.md)
- [WebAuthn passkey setup](./webauthn-passkeys.md)
- [OTP email / SMS setup](./otp-email-sms.md)
