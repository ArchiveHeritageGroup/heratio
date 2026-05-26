# MFA Tenant Enforcement (issue #723)

> Engineer-facing reference for the per-tenant MFA enforcement policy layered on top of the opt-in TOTP / WebAuthn / Email-SMS OTP factors shipped in #690 / #721 / #722. Lives entirely inside `packages/ahg-security-clearance/`.

## Files

- `database/install.sql` — adds `ahg_mfa_policy` table.
- `src/Services/MfaPolicyService.php` — `policyFor`, `requiresMfa`, `inGrace`, `setPolicy`, `resetToGlobalDefault`, `listAllForAdmin`.
- `src/Http/Middleware/EnforceMfaPolicy.php` — wired into the web stack via `bootstrap/app.php`, just after `RequireMfaCompletion`.
- `src/Controllers/MfaPolicyController.php` — admin UI at `/admin/security/mfa-policy`.
- `resources/views/mfa-policy/{index,edit}.blade.php` — Bootstrap 5 + bi-* icons.
- `tests/Unit/MfaPolicyServiceTest.php` and `tests/Unit/EnforceMfaPolicyTest.php`.

## Table schema

```sql
CREATE TABLE `ahg_mfa_policy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `enforcement` varchar(32) NOT NULL DEFAULT 'optional',
  `grace_period_days` int NOT NULL DEFAULT 7,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mfa_policy_tenant` (`tenant_id`),
  KEY `idx_mfa_policy_enforcement` (`enforcement`)
);
```

`tenant_id IS NULL` is the **global default row**. `enforcement` is `VARCHAR(32)` per the project-wide rule against MySQL `ENUM`; the vocabulary lives in `ahg_dropdown` taxonomy `mfa_enforcement`, auto-seeded on first boot.

## Resolution order

`MfaPolicyService::policyFor(?int $tenantId)`:

1. `WHERE tenant_id = $tenantId`
2. `WHERE tenant_id IS NULL`  (global default row, seeded by service provider)
3. synthetic `('optional', 7)` fallback when the table has no rows at all.

## Decision logic

`requiresMfa(User $user)` short-circuits at the first false:

- `off` or `optional` -> false (no enforcement).
- `required_for_admins` AND user is not admin/editor -> false.
- User already has a verified TOTP / WebAuthn / OTP factor -> false.
- Otherwise -> true.

`inGrace(User $user)`:

- `grace_period_days <= 0` -> false (enforce immediately).
- Anchor = `MAX(policy.updated_at, user.created_at)`.
- `time() < anchor + (days * 86400)` -> true.

## Middleware placement

`EnforceMfaPolicy` is appended to the web group in `bootstrap/app.php`, immediately after `RequireMfaCompletion`. This ordering matters: `RequireMfaCompletion` handles the post-login MFA challenge (user has a factor, must verify); `EnforceMfaPolicy` handles the policy gate (user does not have a factor yet but the tenant requires one). Putting them the other way round would let a mid-challenge user be bounced to the enrolment page.

Allowed paths (always pass through):

- `security-clearance/setup-2fa`, `confirm-2fa`, `recovery-codes`
- `security/2fa/webauthn/*`, `security/2fa/otp/*`
- `security-clearance/two-factor`, `verify-2fa`, `two-factor/choose`
- `logout`

In grace -> flash `mfa_policy_grace`, pass through. Otherwise -> redirect to `security-clearance.setup-2fa` with a `mfa_policy_required` flash.

## Tenant resolution

`MfaPolicyService::resolveUserTenantId(User $user)`:

1. `ahg_tenant_user WHERE user_id=? AND is_primary=1`
2. `ahg_tenant_user WHERE user_id=?` (oldest assignment)
3. `TenantContext::currentId()` (host -> session -> default)
4. `null` (single-tenant install -> global default applies)

## Routes

- `GET  /admin/security/mfa-policy` -> `index`
- `GET  /admin/security/mfa-policy/{tenantId}/edit` -> `edit` (`tenantId=0` is the global default)
- `POST /admin/security/mfa-policy/{tenantId}` -> `update` (`acl:update`)
- `POST /admin/security/mfa-policy/{tenantId}/reset` -> `reset` (`acl:update`)

All four sit behind `Route::middleware('admin')` from the package's `routes/web.php`.

## Test surface

`tests/Unit/MfaPolicyServiceTest.php`:

- policyFor() resolves tenant -> global -> synthetic.
- requiresMfa() honours all four enforcement values.
- requiresMfa() returns false once a factor is enrolled.
- inGrace() respects the period and clamps at zero.
- setPolicy() upserts, resetToGlobalDefault() deletes.

`tests/Unit/EnforceMfaPolicyTest.php`:

- Anonymous pass-through.
- Allowed-path pass-through.
- Policy not requiring MFA -> pass-through.
- In-grace -> pass-through with `mfa_policy_grace` flash.
- Out-of-grace -> 302 to setup-2fa.
