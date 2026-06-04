# PSIS (AtoM-AHG) field-level redaction - architecture

Field-level structured redaction of archival-description metadata on PSIS
(AtoM-AHG, `ahgPrivacyPlugin`). Twin of the Heratio Laravel implementation
(`ahg-privacy`, issue #1108). Tracked as atom-ahg-plugins#130; complete as of
v3.53.2.

This is distinct from PDF/visual redaction (which redacts digital objects). It
hides individual metadata fields on the rendered description and in the REST
API for viewers without access.

## Data model

- `privacy_reason` - seeded reason vocabulary (personal_data, special_category,
  access_request id 8, ...).
- `information_object_privacy` - one profile per IO (reason, status, legal basis).
- `information_object_privacy_field` - per-field rule (field_name, redaction_type
  full/partial/pseudonymised, pattern, reason).
- `information_object_privacy_log` - audit row per decision/access.
- `privacy_dsar_object` - DSAR to IO scope link; pre-populates profiles for
  in-scope descriptions.

## Components (lib/Service)

- `PrivacyRedactionService` - engine (full / partial[email_partial, phone_partial,
  id_last4] / pseudonymised), profile/field CRUD, `applyRedaction()`,
  `redactPayload()` (REST), `prepopulateForDsar()`, audit log.
- `RedactionContentFilter` - applies redaction to the rendered IO view for
  unauthorised viewers via the Symfony `response.filter_content` event. Covers
  the i18n text fields plus `creator_dates` (actor dates of existence) and
  `event_dates`. No base-AtoM/theme files modified.
- `RedactionAccess` - single authority for the bypass rule, shared by the web
  filter and the REST API: staff (administrator/editor), or an authenticated
  user with an approved/unexpired `research_researcher` agreement. Fail-closed.

## Surfaces

- Admin: `privacyAdmin/redactionManage` (mark/remove field rules; shows linked
  visual-redaction region count).
- DSAR: `privacyAdmin/dsarScope` (add IOs to scope -> profile pre-populated;
  auto-pre-populate when the DSAR moves to `processing`).
- REST API: `apiv2/descriptions` read + browse redact for non-`admin`-scope keys.

## Bypass rule (who sees the full record)

Staff, or authenticated researchers with an active access agreement
(`research_researcher.status='approved'` and `expires_at` null or in the
future). Everyone else gets the redacted view, consistently across the HTML
view and the JSON API.

## Heratio counterpart

The Laravel side (`packages/ahg-privacy`) implements the same model under
`ahg_*`-free table names (`information_object_privacy*`, `privacy_dsar_object`)
with `PrivacyRedactionService`, `ApplyRedactionMiddleware`, and a response-
injection `InjectFieldRedactionPanel` for the locked IO show page (issue #1108).
