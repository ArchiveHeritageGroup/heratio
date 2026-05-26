# MFA Phase 3 — Email + SMS OTP factor (Heratio #722)

Third opt-in sibling factor on top of TOTP (#690) and WebAuthn passkeys
(#721). Implemented entirely inside `packages/ahg-security-clearance/`
plus the LoginController + RequireMfaCompletion middleware (which were
already MFA-aware from the earlier phases).

## What it gives operators / users

- Every user can enrol any number of email and SMS OTP destinations
  alongside their TOTP authenticator and passkeys. Any verified factor
  satisfies the post-login MFA gate.
- Email OTP is the lowest-friction fallback — no authenticator app, no
  passkey hardware, just the address already on file.
- SMS OTP works the same way once an operator has wired up an SMS
  gateway.
- The login chooser auto-grows to offer every factor the user has
  enrolled. Single-factor users skip the chooser and go straight to the
  matching verify page.

## Storage

Two new tables, both auto-installed by the
`AhgSecurityClearanceServiceProvider::boot()` probe:

- `ahg_otp_factor` — per-user enrolment row. `factor_type` is `email` or
  `sms`. `verified_at` stays NULL until the user proves they own the
  destination by entering the first delivered code.
- `ahg_otp_challenge` — short-lived 6-digit codes. The plaintext is
  **never** persisted — only a SHA-256 hash. `attempts` counts every
  failed verify so the audit trail survives a cache flush. `consumed_at`
  is stamped on first hit; a consumed challenge cannot be replayed.

## Service contract

`AhgSecurityClearance\Services\OtpService` exposes:

- `enrol(int $userId, string $type, string $destination, string $label)`
  - creates a pending factor row and dispatches the first code.
- `verifyEnrolment(int $userId, int $factorId, string $code): bool`
  - validates the first code, sets `verified_at` on success.
- `sendChallenge(User, AhgOtpFactor): AhgOtpChallenge`
  - rate-limited to one challenge per 60s per factor.
- `verify(int $userId, int $factorId, string $code): bool`
  - login-time verification. Locks the factor for 15 min after 5 failed
    attempts inside the same 15-min window (cache-backed; falls back to
    the `attempts` column).
- `factorsFor(int $userId): Collection`
  - all enrolled factors (verified + pending) for the management UI.
- `userHasOtp(int $userId): bool`
  - has at least one verified OTP factor. Drives the LoginController
    MFA-gate decision.
- `deleteFactor` / `disable` — user-initiated removal + admin override.

## Senders

- **Email**: `AhgSecurityClearance\Mail\OtpCodeMail` uses the
  `LocaleAwareMailable` trait so the body is rendered in the
  recipient's `preferred_locale`. Templates live at
  `packages/ahg-security-clearance/resources/views/emails/{en,af}/otp-code.blade.php`
  and extend the shared `emails._layout` for branded styling.
- **SMS**: pluggable via `SmsGatewayInterface`. Concrete drivers:
  - `NullSmsGateway` — logs to `laravel.log` only. Default for dev / CI.
  - `HttpSmsGateway` — POSTs `to=…&body=…` (form-encoded) to a
    configurable HTTP endpoint. Suitable for any HTTP-based SMS
    provider; per-provider native drivers (Twilio, Vonage, Clickatell)
    are a Phase 2 follow-up.

The active driver is picked by the `sms_gateway` ahg_setting key
(`null` | `http`). When `http` is selected the operator must also set:

- `sms_http_endpoint` — full URL of the provider API.
- `sms_http_token` — optional Bearer token.
- `sms_http_to_field` (default `to`).
- `sms_http_body_field` (default `body`).
- `sms_http_method` (default `POST`).

## Routes

All routes live under `/security/2fa/otp/*` to match the existing
`/security/2fa/webauthn/*` namespace:

- `GET  /security/2fa/otp` — management list.
- `GET  /security/2fa/otp/add` — channel / destination picker.
- `POST /security/2fa/otp/enrol` — create factor + send first code.
- `GET  /security/2fa/otp/{id}/verify-enrolment` — enter first code.
- `POST /security/2fa/otp/{id}/verify-enrolment` — confirm.
- `POST /security/2fa/otp/{id}/resend-enrolment` — resend (rate-limited).
- `POST /security/2fa/otp/{id}/delete` — remove factor.
- `GET  /security/2fa/otp/verify` — login-time picker.
- `POST /security/2fa/otp/assert/begin` — send code at login.
- `POST /security/2fa/otp/assert/complete` — verify + clear MFA gate.

All three assert endpoints are on the
`RequireMfaCompletion::ALLOWED_PATHS` whitelist so users with only an
OTP factor enrolled can still hit them while gated.

## Login-flow integration

`LoginController::login()` now checks `userHasOtp()` alongside the
existing TOTP + WebAuthn probes. If at least one factor is enrolled,
`pending_mfa` is set on the session and the user is redirected to
`/security-clearance/two-factor`, which routes to:

- the chooser (`/security-clearance/two-factor/choose`) when two or
  more factors are enrolled;
- the TOTP form for TOTP-only users;
- the WebAuthn verify page for passkey-only users;
- the OTP verify page for OTP-only users.

The chooser blade auto-shows every enrolled factor as a tile.

## Threat model

- Codes are 6 digits (1,000,000 keyspace) with 10-minute TTL and a
  per-factor 5-attempt cap inside a 15-minute window. Best-case attacker
  effort to brute-force a single code is 200k attempts → impossible
  inside the lockout window.
- The plaintext code never lands in the database; only its SHA-256 hash
  does. The cache layer is a soft optimisation for the lockout counter
  — the `attempts` column on `ahg_otp_challenge` keeps a hard record so
  a cache flush cannot reset the cap mid-attack.
- Throttle (60s between `sendChallenge` calls) blocks an attacker who
  is also somehow able to trigger sends, from flooding the user's inbox
  or burning SMS credit.
- The SMS gateway is opt-in. With no operator configuration the system
  uses `NullSmsGateway` which only logs — so a careless deploy cannot
  accidentally start sending SMS to live numbers.

## Open follow-ups (out of scope for #722)

- Per-provider native SMS drivers (Twilio, Vonage, Clickatell). The
  generic HTTP gateway covers most South African and African providers
  via their HTTP/REST surfaces today.
- Backup-code minting for OTP factors (TOTP has them via
  `user_mfa_recovery_code`; OTP currently relies on the user having a
  second factor enrolled as the backup).
- Admin UI to inspect / reset per-user OTP factors. Today admins can
  call `OtpService::disable($userId)` programmatically or wait for the
  next admin-side surface to ship.
