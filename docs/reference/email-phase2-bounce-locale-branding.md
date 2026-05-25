# Email Phase 2: Bounce handling, localised templates, per-tenant branding, missing event types

Phase 2 of #674 (Email + notifications). Ships in Heratio v1.95.0. Phase 1
(queued dispatch on Mailables) was v1.72.1. This doc explains the four
deliverables - what was built, where the seams are, what's still open.

## 1. Bounce handling

Webhook endpoint receives bounce + complaint events from the upstream mail
provider and reflects them into the `user` table so subsequent send paths
can suppress to a bouncing address.

- **Endpoint:** `POST /webhooks/email/bounce` (no auth/session; HMAC-validated)
- **Controller:** `app/Http/Controllers/EmailBounceController.php`
- **Shared secret:** `ahg_settings.email_bounce_webhook_secret` (auto-seeded
  by the migration with 24 random bytes; rotate by updating the row)
- **Signature header:** `X-AHG-Signature: sha256=<hmac-hex>` (also accepts
  `X-Hub-Signature-256` or `X-Postmark-Signature`)
- **Log table:** `ahg_email_bounce` (id, email, bounce_type, bounce_subtype,
  reason, message_id, provider, occurred_at, payload_json, processed_at,
  created_at)
- **User effect column:** `user.email_bounced_at` (DATETIME, nullable;
  set on hard bounce + complaint + soft-promotion)

### Provider payload mapping

The controller auto-detects the shape and normalises to the same flat
event structure internally.

| Provider  | Detection key             | Bounce-type mapping                                              |
|-----------|---------------------------|------------------------------------------------------------------|
| Postmark  | `RecordType` / `Type`+`Email` | `HardBounce`/`BadRecipient`/`Unknown` -> hard; `Transient`/`MailboxFull` -> soft; `SpamComplaint` -> complaint |
| Amazon SES| `notificationType`        | `bounce.bounceType=Permanent` -> hard; `Transient` -> soft; `Complaint` -> complaint |
| SparkPost | `msys`                    | `bounce_class` 10/20/21/30/90 -> hard; 40/50/60/70 -> soft; `type=spam_complaint` -> complaint |
| Mailgun   | `event-data`              | `failed`+`permanent` -> hard; `failed`+`temporary` -> soft; `complained` -> complaint |
| Generic   | `email` + `type`          | `type` value drives the mapping directly                         |

Force a provider via `ahg_settings.email_bounce_webhook_provider`
(values: `postmark`, `ses`, `sparkpost`, `mailgun`, `generic`).

### Soft-bounce promotion

5 soft bounces from the same address in a rolling 30-day window get
promoted to hard (sets `user.email_bounced_at`). Tunable via the constants
on `EmailBounceController`.

### Gate

`App\Services\EmailSuppressionGate::isSuppressed(string $email)` - call
before `Mail::to(...)->queue($mailable)` to honour the bounce list.
Wired into `LoginController::submitPasswordReset()` as the reference
integration; other dispatch sites should follow the same pattern.

`EmailSuppressionGate::clear($email)` clears the hold (admin action when
a recipient has confirmed the deliverability problem is fixed).

## 2. Localised templates

- **Trait:** `App\Mail\Concerns\LocaleAwareMailable` (used by every new
  Mailable; the existing PasswordResetMail was retrofitted as the
  reference impl).
- **View resolution order:** `emails.<locale>.<base>` -> `emails.en.<base>` ->
  `emails.<base>` (legacy fallback). Driven by
  `LocaleAwareMailable::localisedView($base)`.
- **Locale resolution order:** explicit `$this->locale` -> `$user->preferred_locale` ->
  lookup by recipient email in `user.preferred_locale` -> `config('app.locale')`.
- **Templates shipped:** `resources/views/emails/en/password-reset.blade.php`
  + `resources/views/emails/af/password-reset.blade.php` (Afrikaans).
- **User column added:** `user.preferred_locale VARCHAR(10) NULL` - profile
  editor can be wired to set it; defaults to NULL meaning "use app locale".

Subject strings go through `__()` so the envelope speaks the same
language as the body. `App::setLocale($this->resolveEmailLocale())` is
called inside `envelope()` and `content()` so `__()` resolves correctly
in the worker context where the request-locale isn't available.

## 3. Per-tenant branding

- **Table:** `ahg_tenant_email_branding(tenant_id PK, logo_url,
  primary_color, secondary_color, footer_text_html, sender_name,
  sender_email_override, updated_at)` - one row per tenant, FK to
  `ahg_tenant.id` on cascade delete.
- **Service:** `App\Services\TenantEmailBranding` - resolves the active
  tenant via `AhgMultiTenant\Services\TenantService::getCurrentTenant()`
  in web context, or via an explicit `tenantId` injected by the
  dispatcher when running queued.
- **Layout:** `resources/views/emails/_layout.blade.php` injects the
  service (`@inject('branding', ...)`) and reads `logoUrl()`,
  `primaryColor()`, `secondaryColor()`, `footerHtml()` for the chrome.
  Falls back to Heratio defaults (`#0d6efd`, `#6c757d`, brand-neutral
  footer) when no row exists.
- **Sender:** `senderName()` / `senderEmail()` - Mailables that want to
  honour the per-tenant From should call `app(TenantEmailBranding::class)`
  in `envelope()` (PasswordResetMail does this as the reference impl).
- **Admin UI:** `/admin/email/branding` - one row per tenant, dropdown to
  pick the tenant, form fields for logo URL, primary/secondary colours
  (with HTML5 colour picker), footer HTML, sender name + email
  override. Uses Bootstrap 5 + bi-* icons.

## 4. Missing event types

Picked from the audit table in the issue body. Three new Mailables, each
ShouldQueue, each with HTML + plain-text views, each locale-aware:

| Mailable | Package | Trigger (TODO to wire) |
|----------|---------|------------------------|
| `WorkflowTaskOverdueMail` | `ahg-workflow` | Daily scheduled command - tasks where `due_at < NOW()` and `last_notified_at < (now - nag_interval)` |
| `DoiMintedMail` / `DoiFailedMail` | `ahg-doi-manage` | DoiService::mint() success / failure paths |
| `SharePointSyncErrorMail` | `ahg-sharepoint` | SharePoint sync job catch-all when run accumulates errors or fails terminally |

Dispatch wiring is intentionally NOT included in this release - the
relevant trigger points live in locked packages or in service code where
the call shape varies by trigger condition. The Mailables stand alone
and are easy to dispatch from anywhere.

## What's still open after Phase 2

- Dispatch wiring for the three new event Mailables (see table above).
- Per-user mute / snooze (Phase 8 in the issue plan) - the user
  preference layer needs its own table.
- `sent_emails` audit-trail table (originally Phase 2 in the issue plan;
  deferred so this phase ships in one release).
- Click + open tracking (separate phase; needs pixel + redirect endpoints).
- Inline images (low priority; Phase 9 in the issue plan).
- Translation of every other existing Mailable's blade view from the
  legacy `emails.<base>` path into `emails.en.<base>` + `emails.af.<base>`
  (only password-reset shipped today; the trait falls back silently so
  existing Mailables keep working).

## Files added / modified

```
database/migrations/2026_05_25_020000_create_email_phase2_tables.php   NEW
app/Http/Controllers/EmailBounceController.php                          NEW
app/Http/Controllers/EmailBrandingController.php                        NEW
app/Mail/Concerns/LocaleAwareMailable.php                               NEW
app/Mail/PasswordResetMail.php                                          MODIFIED
app/Services/TenantEmailBranding.php                                    NEW
app/Services/EmailSuppressionGate.php                                   NEW
app/Http/Controllers/Auth/LoginController.php                           MODIFIED
bootstrap/app.php                                                       MODIFIED
routes/web.php                                                          MODIFIED
resources/views/emails/_layout.blade.php                                NEW
resources/views/emails/en/password-reset.blade.php                      NEW
resources/views/emails/af/password-reset.blade.php                      NEW
resources/views/admin/email-branding.blade.php                          NEW
packages/ahg-workflow/src/Mail/WorkflowTaskOverdueMail.php              NEW
packages/ahg-workflow/resources/views/emails/task-overdue.blade.php     NEW
packages/ahg-workflow/resources/views/emails/task-overdue-text.blade.php NEW
packages/ahg-doi-manage/src/Mail/DoiMintedMail.php                      NEW
packages/ahg-doi-manage/src/Mail/DoiFailedMail.php                      NEW
packages/ahg-doi-manage/resources/views/emails/doi-{minted,failed}.blade.php   NEW
packages/ahg-doi-manage/resources/views/emails/doi-{minted,failed}-text.blade.php NEW
packages/ahg-sharepoint/src/Mail/SharePointSyncErrorMail.php            NEW
packages/ahg-sharepoint/resources/views/emails/sync-error.blade.php     NEW
packages/ahg-sharepoint/resources/views/emails/sync-error-text.blade.php NEW
```
