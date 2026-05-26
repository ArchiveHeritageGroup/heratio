# MFA Phase 2 — WebAuthn / FIDO2 / passkeys (issue #721)

Ships in v1.97.x. Sibling factor to TOTP (issue #690). A user can enrol
either, or both; login flow shows a chooser when both are present.

## Surface

Routes are added in `packages/ahg-security-clearance/routes/web.php`:

| Method | Path | Purpose |
|---|---|---|
| GET  | `/security/2fa/webauthn` | List enrolled passkeys for the current user (auth required) |
| GET  | `/security/2fa/webauthn/add` | Render the enrolment page (label + JS trigger) |
| POST | `/security/2fa/webauthn/register/begin` | Returns `PublicKeyCredentialCreationOptions` JSON for `navigator.credentials.create()` |
| POST | `/security/2fa/webauthn/register/complete` | Validates attestation, persists credential |
| POST | `/security/2fa/webauthn/{id}/delete` | Removes a single enrolled credential |
| GET  | `/security/2fa/webauthn/verify` | Login-time challenge page (JS triggers assert) |
| POST | `/security/2fa/webauthn/assert/begin` | Returns `PublicKeyCredentialRequestOptions` JSON |
| POST | `/security/2fa/webauthn/assert/complete` | Validates assertion, clears `pending_mfa` session flag |
| GET  | `/security-clearance/two-factor/choose` | Chooser shown when both TOTP and WebAuthn are enrolled |

## Database

`ahg_webauthn_credential` — one row per enrolled authenticator:

```sql
CREATE TABLE ahg_webauthn_credential (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  credential_id    VARBINARY(512) UNIQUE NOT NULL,
  public_key       MEDIUMBLOB NOT NULL,         -- Webauthn\PublicKeyCredentialSource serialised as JSON
  attestation_type VARCHAR(32) NOT NULL,
  aaguid           CHAR(36) DEFAULT NULL,
  sign_count       BIGINT UNSIGNED NOT NULL,
  transports       JSON DEFAULT NULL,
  label            VARCHAR(255) NOT NULL,
  last_used_at     DATETIME DEFAULT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_webauthn_user (user_id)
);
```

The provider auto-installs this table on first boot via the same Schema::hasTable
probe + DB::unprepared(install.sql) pattern used for the TOTP tables.

## Composer dependency

```bash
composer require web-auth/webauthn-lib:^5.3
```

5.3.4 is the current stable as of 2026-05-26. Transitive deps:
`paragonie/constant_time_encoding`, `phpdocumentor/reflection-docblock`,
`psr/clock`, `psr/event-dispatcher`, `psr/log`, `spomky-labs/cbor-php`,
`spomky-labs/pki-framework`, `symfony/clock`, `symfony/uid`,
`symfony/property-info`, `symfony/property-access`, `symfony/serializer`,
`symfony/deprecation-contracts`, `web-auth/cose-lib`.

Symfony constraint `^6.4|^7.0|^8.0` aligns with Laravel 12 (Symfony 7.x), so
no conflict is expected. If composer reports a conflict, document it as a
follow-up issue rather than forcing a constraint-loosening change.

## Flow

### Enrolment

1. User hits `/security/2fa/webauthn` (linked from profile / security settings).
2. Clicks "Add passkey" → form with a label field + an `Enrol` button.
3. JS POSTs to `/register/begin`, receives `PublicKeyCredentialCreationOptions` JSON.
4. JS decodes the `challenge`, `user.id`, and `excludeCredentials[].id` from
   base64url to ArrayBuffer, then calls `navigator.credentials.create()`.
5. Browser prompts the user (platform passkey / hardware key / Touch ID etc.).
6. JS POSTs the response (b64url-encoded `clientDataJSON`, `attestationObject`,
   transports) plus the label to `/register/complete`.
7. Server runs `AuthenticatorAttestationResponseValidator::check()` and on
   success persists the `PublicKeyCredentialSource` (serialised via
   `Webauthn\Denormalizer\WebauthnSerializerFactory`).

### Login assertion

1. After password OK, `LoginController::login` checks
   `WebAuthnService::userHasCredential()` (alongside the existing TOTP check).
   If either factor is present, sets `pending_mfa` and redirects to
   `/security-clearance/two-factor`.
2. `SecurityClearanceController::twoFactor()` picks the right view:
   - both factors → chooser
   - passkey only → `/security/2fa/webauthn/verify`
   - TOTP only → original `twofactor/verify.blade.php`
3. Chooser "Authenticator code" link adds `?force_totp=1` so the user can
   bypass the chooser loop and reach the TOTP form.
4. WebAuthn JS triggers `/assert/begin` → `navigator.credentials.get()` →
   `/assert/complete`. On success the controller clears `pending_mfa` and
   writes a `security_2fa_session` row.
5. `RequireMfaCompletion` middleware whitelists `/security/2fa/webauthn/verify`,
   `/security/2fa/webauthn/assert/begin`, `/security/2fa/webauthn/assert/complete`
   and `/security-clearance/two-factor/choose` so users with a passkey can
   clear the gate without the TOTP code.

## RP ID

The Relying Party ID is resolved at request time via `Request::getHost()`.
That means a user enrolled on `heratio.example.org` cannot authenticate at
`archive.example.org` — by design. If a single Heratio instance is reached
over multiple hostnames, each will need its own enrolment.

## HTTPS-only

WebAuthn rejects HTTP origins (except `localhost` / `127.0.0.1`). On a
production deployment behind nginx, ensure HTTPS is terminated and
`X-Forwarded-Proto: https` is forwarded so Laravel sees `request()->isSecure()`
as true. The setup page surfaces a warning when neither condition holds.

## File map

| Path | Purpose |
|---|---|
| `packages/ahg-security-clearance/src/Services/WebAuthnService.php` | Enrol + assert backend; implements `PublicKeyCredentialSourceRepository` |
| `packages/ahg-security-clearance/src/Controllers/WebAuthnController.php` | HTTP layer (8 actions) |
| `packages/ahg-security-clearance/routes/web.php` | Routes added (search for issue #721) |
| `packages/ahg-security-clearance/resources/views/webauthn/list.blade.php` | List + Add UI |
| `packages/ahg-security-clearance/resources/views/webauthn/setup.blade.php` | Per-credential enrolment page (JS triggers `navigator.credentials.create`) |
| `packages/ahg-security-clearance/resources/views/webauthn/verify.blade.php` | Login-time assert page (JS triggers `navigator.credentials.get`) |
| `packages/ahg-security-clearance/resources/views/webauthn/chooser.blade.php` | "TOTP or passkey?" picker |
| `packages/ahg-security-clearance/database/install.sql` | `ahg_webauthn_credential` table appended |
| `app/Http/Controllers/Auth/LoginController.php` | MFA gate now triggers on either factor |
| `app/Http/Middleware/RequireMfaCompletion.php` | WebAuthn paths whitelisted |
| `packages/ahg-security-clearance/src/Controllers/SecurityClearanceController.php` | `twoFactor()` factor-aware routing + new `twoFactorChooser()` |

## Open follow-ups

- A per-credential rename UI (currently the label is fixed at enrolment).
- A user-disable-all-passkeys action (admins can disable via
  `WebAuthnService::disable()` which `SecurityClearanceController::removeTwoFactor`
  should also call when wiping a user's MFA).
- Per-tenant policy enforcement for passkeys (covered by sibling #723).
- Conditional UI (`mediation: 'conditional'`) for password-less sign-in is
  out of scope for #721 — first-pass is password + passkey.
