# ahg/sharepoint (Heratio package)

Microsoft 365 SharePoint integration for Heratio. One-way ingest from SharePoint with eventual records-handoff via Graph webhooks and federated search across both surfaces.

**Status:** Phase 1 scaffold (v0.1.0). Tables, service provider, and command/controller stubs in place; service implementations are TODO.

**Plan:** [`atom-extensions-catalog/docs/technical/ahgSharePointPlugin_Implementation_Plan.md`](https://github.com/ArchiveHeritageGroup/atom-extensions-catalog/blob/main/docs/technical/ahgSharePointPlugin_Implementation_Plan.md)

**AtoM counterpart:** `/usr/share/nginx/archive/atom-ahg-plugins/ahgSharePointPlugin/` — schema and feature parity required (no drift).

## Phases

1. **Foundation** (this scaffold) — tenant config, drive registration, manual delta sync (`sharepoint:sync`), settings UI, audit-trail.
2. **Webhooks** — subscription lifecycle, records handoff, Purview retention-label mapping. Gated on a half-day verification spike.
3. **Discovery** — AtoM-side federated search tab (staff-only), M365-side Microsoft Search connector feed.

## Install (once services are implemented)

Register the package via the root `composer.json` (likely already present in the monorepo `require` block) and:

```bash
cd /usr/share/nginx/heratio
composer dump-autoload
php artisan migrate --path=packages/ahg-sharepoint/database/migrations
php artisan sharepoint:test-connection --tenant=1
```

## Tables

`sharepoint_tenant`, `sharepoint_drive`, `sharepoint_mapping`, `sharepoint_sync_state`, `sharepoint_subscription`, `sharepoint_event`. Plus an additive migration on `ingest_session` (adds `source` and `source_id`).

## Locked architectural decisions

See plan §2. Highlights:
- Hand-rolled Graph client (no microsoft/microsoft-graph SDK)
- Settings section added to `ahg-settings` package (mirror of AtoM's `ahgSettingsPlugin` edit)
- `firebase/php-jwt` for Phase 3 inbound JWT validation
- Webhook URL: same path as AtoM target (configurable per install via `ahg_settings`)
- Federated search gated to Heratio staff (editor/admin) only

## Parity rule

Any feature change here MUST land in the AtoM plugin in the same release, and vice versa. Schema is byte-equivalent (modulo install.sql header). Drift is the bug this requirement exists to prevent.
