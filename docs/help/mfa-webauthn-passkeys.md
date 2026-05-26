# Sign in with a passkey (WebAuthn / FIDO2)

Heratio supports **passkeys** as a second factor for sign-in, alongside the
authenticator-app (TOTP) codes documented in
[Two-factor authentication](mfa-totp). A passkey is anything your browser
treats as a WebAuthn credential — a hardware security key (YubiKey, SoloKey,
Token2, etc.), your device biometric (Touch ID, Face ID, Windows Hello), or
a platform passkey synced through iCloud Keychain / Google Password Manager
/ 1Password / Bitwarden / Dashlane.

You can enrol one or many. Each one is its own factor; losing access to one
does not lock you out of the others.

## When to use a passkey instead of a TOTP code

- **Hardware key (YubiKey etc.)** — best for high-assurance roles
  (administrators, compliance officers, anyone with a restricted clearance).
  Phishing-resistant: the key only signs for the domain it was enrolled on.
- **Device biometric (Touch ID / Windows Hello)** — most convenient for daily
  use; no typing of 6-digit codes.
- **Platform passkey (synced)** — work across all your devices once you sign
  in to your password manager.

You can have all three at once. If you also have TOTP enrolled, the sign-in
flow asks you which to use.

## Enrol a passkey

1. Sign in to Heratio.
2. Open your profile → **Security** → **Passkeys** (or go straight to
   `/security/2fa/webauthn`).
3. Click **Add passkey**.
4. Give the passkey a label so you can recognise it later
   (e.g. *YubiKey 5C — work*, *MacBook Touch ID*).
5. Click **Enrol passkey**. Your browser will prompt you to touch your key,
   approve with your fingerprint / face, or pick a synced passkey.
6. Once enrolment completes you are returned to the passkey list. The new
   entry is shown with its label, transports (USB / NFC / BLE / internal /
   hybrid) and "enrolled" timestamp.

Repeat for each key or device you want enrolled.

## Sign in with a passkey

The flow depends on what you have enrolled:

| Enrolled | Sign-in prompts |
|---|---|
| TOTP only | 6-digit code page (unchanged) |
| Passkey only | Passkey verify page → press button → confirm on device |
| Both | Chooser page → pick **Passkey** or **Authenticator code** |

When you pick **Passkey** the browser asks you to confirm on the device.
A successful response clears the second-factor gate and you are returned to
wherever you were heading before sign-in.

If your passkey fails (wrong device, key not plugged in, biometric refused),
the verify page surfaces the error. You can retry, or click
**Use authenticator-app code instead** to fall back to TOTP — provided you
have it enrolled.

## Remove a passkey

1. Go to **Profile → Security → Passkeys**.
2. Find the passkey you want to remove and click **Delete**.
3. Confirm. The credential is removed server-side and from that point on it
   cannot satisfy sign-in.

Always make sure you have at least one working second factor (either a
remaining passkey, a TOTP enrolment, or printed recovery codes) before
removing your only passkey — otherwise you may lock yourself out.

## Requirements

- **HTTPS.** WebAuthn refuses to enrol or assert on plain HTTP. Localhost
  is the only exception, used for development. If your Heratio is behind a
  reverse proxy, make sure it terminates TLS and forwards
  `X-Forwarded-Proto: https`.
- **Modern browser.** Chrome 67+, Firefox 60+, Safari 14+, Edge 18+ all
  support WebAuthn. Internet Explorer is not supported.
- **Same hostname.** A passkey enrolled on `archive.example.org` does not
  work on `heratio.example.org`. This is a WebAuthn property
  (the *Relying Party ID*), not a Heratio choice.

## Troubleshooting

**"WebAuthn requires HTTPS" warning on the enrol page** — your Heratio is
being served over HTTP. Switch to HTTPS or test from `https://localhost`.

**"NotAllowedError" from the browser** — you cancelled the prompt or it
timed out. Try again and confirm within 60 seconds.

**"InvalidStateError" from the browser** — that authenticator is already
enrolled on this account. Pick a different key or device.

**"attestation verification failed" from Heratio** — the response from your
device could not be validated. Most often this means the page reloaded
between begin and complete, dropping the challenge from the session. Reload
the enrol page and try again.

**Lost every enrolled passkey AND every TOTP code AND every recovery code** —
ask an administrator to wipe your second factor from the security clearance
admin page. You will then sign in with just your password and can re-enrol.

## See also

- Two-factor authentication (TOTP) settings — `/security-clearance/setup-2fa` for the authenticator-app companion factor.
- Recovery codes — `/security-clearance/recovery-codes` shows your remaining single-use backup codes (set on TOTP enrolment).
- *Reference for operators:* `docs/reference/mfa-phase-2-webauthn.md` in the repo (file layout, routes, RP ID handling).
