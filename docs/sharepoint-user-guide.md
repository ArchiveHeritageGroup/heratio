# SharePoint Integration

Microsoft 365 SharePoint integration for AtoM Heratio and Heratio standalone. One-way ingest from SharePoint into the archive, with eventual records-handoff via Graph webhooks and federated search across both surfaces.

This guide covers what the integration does, how to set it up against an Azure AD tenant, and how to use it day-to-day.

---

## What it does

Two complementary modes, both backed by the same ingest pipeline:

### Mode A — Manual push (Phase 2.B)

A SharePoint user clicks **Send to Archive** on selected documents in any document library. A dialog opens (delivered via the SharePoint Framework command set), the user picks a target repository + parent description and reviews/edits the metadata, then submits. The system fetches the file via Microsoft Graph using **on-behalf-of** auth (so SharePoint permissions are preserved), creates a description with the digital object attached, and records the pushing user as the actor in the audit trail.

### Mode B — Auto / declare (Phase 2.A)

When a Microsoft Purview retention label in a configured allowlist is applied to a SharePoint document, Microsoft Graph fires a webhook to the archive. The archive validates the notification, fetches the document and its metadata, and ingests it automatically using the drive's default repository / parent / sector. Items whose retention label is NOT in the allowlist are ignored.

Both modes share:
- Per-drive column mapping (SharePoint columns → archival fields)
- Retention label → disposition mapping (security clearance, embargo, parent placement)
- Audit trail entry per ingested item
- The existing Ingestion pipeline (validation, AI processing, OAIS packages if enabled)

---

## Phase status

| Phase | Includes | Status |
|-------|----------|--------|
| Phase 1 — Foundation | Tenant config, drive registration, manual delta sync (`sharepoint:sync`), settings UI | **Implemented** |
| Phase 2.A — Webhooks (auto / declare) | Subscription lifecycle, webhook receiver, retention mapping, label allowlist filter | **Scaffold complete; runs end-to-end against a real tenant once the AAD app is registered** |
| Phase 2.B — Manual push | SPFx command set, AAD JWT validation, OBO file fetch, user mapping admin | **Scaffold complete; SPFx package needs `gulp bundle`; user provisioning needs platform-specific wiring** |
| Phase 3 — Discovery surfaces | Federated search tab, M365 Microsoft Search connector | Planned |

---

## Setup

### 1. Azure AD app registration (one-time per tenant)

In your Azure AD admin portal:

1. **Create an app registration**. Name it something like `AtoM Heratio SharePoint Integration`. Single-tenant is fine for AHG-internal use.
2. **Note the values** you will plug into the archive admin UI:
   - **Directory (tenant) ID**
   - **Application (client) ID**
3. **Add a client secret**. Note the value — it is shown only once. The archive encrypts it at rest before storing.
4. **Add API permissions** (then grant admin consent for each):
   - Microsoft Graph → Application → `Sites.Read.All`
   - Microsoft Graph → Application → `Files.Read.All`
   - Microsoft Graph → Delegated → `Files.Read.All` *(only needed for Mode A — manual push)*
5. **Expose an API** scope on this same app registration *(only needed for Mode A)*:
   - Application ID URI: `api://<client-id>` (default)
   - Add a scope, e.g. `SharePointPush.Submit` — admin consent required.
6. **Authorize SharePoint Online** to use it: under **API permissions → SharePoint** the request will appear once SPFx is installed; admin grants consent there.

### 2. Install the dependency and schema

On a server shell:

```bash
# Pull the JWT library (Phase 2.B)
cd /usr/share/nginx/archive/atom-framework      # AtoM
composer update firebase/php-jwt --no-dev

cd /usr/share/nginx/heratio                      # Heratio standalone
composer install                                  # firebase/php-jwt is in ahg/sharepoint composer.json

# Install schema + reporting view
php symfony sharepoint:install                    # AtoM
php artisan sharepoint:install                    # Heratio standalone
```

`sharepoint:install` is idempotent — safe to re-run after upgrades.

### 3. Configure the tenant in the admin UI

In **Admin > AHG Settings > SharePoint Integration**:

- Toggle **SharePoint integration enabled**.
- Toggle **Records handoff (auto/declare)** if Mode B is wanted.
- Toggle **Auto-create user on first manual push** if Mode A is wanted (recommended on; turn off to require pre-provisioning).
- Set the **Public webhook URL** — must be HTTPS-reachable from Microsoft, e.g. `https://psis.theahg.co.za/sharepoint/webhook` (AtoM) or your equivalent for Heratio standalone.
- Provide the **Retention label → disposition map** as a JSON object keyed by Purview compliance tag, e.g.:
  ```json
  {
    "Archive-Permanent":  { "level_of_description_id": 12, "parent_id": 345 },
    "Confidential-7yr":   { "security_classification_id": 3, "embargo_until_field": "_ComplianceTagWrittenTime", "embargo_offset_days": 2555 },
    "default":            { "level_of_description_id": 12 }
  }
  ```

Then go to **SharePoint admin** (`/sharepoint/tenants`) and create a tenant row:

- Friendly name (e.g., "AHG Production")
- Tenant ID (from step 1.2)
- Client ID (from step 1.2)
- Client secret (from step 1.3) — encrypted on save; the field shows blank on next view.

Verify it works:

```bash
# AtoM
php symfony sharepoint:test-connection --tenant=1
# Heratio standalone
php artisan sharepoint:test-connection --tenant=1
```

Expected output: `Connection OK` plus a list of up to 5 SharePoint sites the app can see.

### 4. Register drives for ingest

At `/sharepoint/drives`:

1. Pick a SharePoint site (autocomplete browses sites the app has access to).
2. Pick a document library (drive) within that site.
3. Set the drive's **sector** (archive / museum / library / gallery / dam) and **default repository / parent description**.
4. Optionally set the **auto-ingest label allowlist**: a JSON array of compliance tag names, e.g. `["Archive-Permanent", "Records-Manage"]`. When empty/null, Mode B is OFF for this drive (Mode A — manual push — still works). Items whose `_ComplianceTag` is NOT in the list are silently skipped, even if they otherwise qualify.
5. Toggle **ingest_enabled**.

Edit the per-drive **column mapping** at `/sharepoint/drives/<id>/mapping`. Map source SharePoint columns (e.g. `Title`, `Author`, `fields._ComplianceTag`) to AtoM target fields (e.g. `title`, `creators`, `_compliance_tag`). Supported transforms: `date_iso`, `html_strip`, `lowercase`, `uppercase`. The `fields.` prefix tells the mapper to read from the listItem fields object rather than the driveItem itself.

### 5. Mode A — install the SPFx package

The SharePoint Framework command set lives at `atom-extensions-catalog/spfx/atom-archive-push/`. Build and install once per tenant:

```bash
cd atom-extensions-catalog/spfx/atom-archive-push
npm install              # Node 18.x or 20.x required
gulp bundle --ship
gulp package-solution --ship
# Output: solution/atom-archive-push.sppkg
```

Then in the SharePoint admin centre:

1. Upload `atom-archive-push.sppkg` to the **Tenant App Catalog**.
2. Approve the Microsoft Graph permission request (`Files.Read.All`) when prompted.
3. Install the app on every site that should expose the **Send to Archive** button.
4. Configure the SPFx command set's tenant properties:
   - `atomBaseUrl` — e.g. `https://psis.theahg.co.za` or your Heratio URL
   - `atomTenantId` — the `sharepoint_tenant.id` row created in step 3 (numeric)

The button now appears in the document-library command bar whenever one or more files are selected.

### 6. Mode B — create webhook subscriptions

For each ingest-enabled drive that should auto-ingest:

```bash
# AtoM
php symfony sharepoint:subscribe --drive=1
# Heratio standalone
php artisan sharepoint:subscribe --drive=1
```

This creates **two subscriptions** per drive — one on the drive's content (catches file additions/changes) and one on the underlying list (catches retention-label changes that may not surface as content changes). Status visible at `/sharepoint/subscriptions`.

Subscriptions expire (driveItem subscriptions max 30 days). Schedule the renewal cron:

```cron
# crontab -e
5  * * * * cd /usr/share/nginx/archive && php symfony sharepoint:renew-subscriptions  >> /var/log/atom/sharepoint-renew.log 2>&1
15 * * * * cd /usr/share/nginx/archive && php symfony sharepoint:sync                  >> /var/log/atom/sharepoint-sync.log 2>&1
```

Both entries are also documented under **Admin > AHG Settings > Cron Jobs > External Integrations**.

---

## Day-to-day usage

### Mode A — pushing manually

1. Open a SharePoint document library, select one or more files.
2. Click **Send to Archive** in the command bar.
3. Review the metadata (prefilled by the column mapping), pick the target repository and parent description, edit any fields that need correction, and submit.
4. The dialog shows the ingest job ID and polls until completion. The new descriptions appear in AtoM/Heratio with the digital objects attached.

### Mode B — automatic on label

1. In SharePoint, apply a retention label that's in the drive's allowlist (e.g., set the label manually, or have a Purview auto-apply policy assign it).
2. Within ~5 minutes, Microsoft Graph fires a webhook. The archive ingests the document.
3. Watch progress at `/sharepoint/events`. Status flow: `received` → `queued` → `processing` → `completed` (or `failed` / `skipped_duplicate` / `skipped_not_allowlisted`).

### Inspecting health

```bash
php symfony sharepoint:status   # AtoM
php artisan sharepoint:status   # Heratio
```

Prints tenants, drives, sync state, subscription expiries, event counts (last 24h).

### Manual delta sync (recovery / backfill)

If webhooks miss events (transient delivery failure, expired subscription) the hourly `sharepoint:sync` cron picks them up via Graph delta query. Force a full refresh with:

```bash
php symfony sharepoint:sync --drive=1 --full
```

---

## Admin pages

| Path | Purpose |
|------|---------|
| `/sharepoint` | Dashboard: tenants, drives, recent events |
| `/sharepoint/tenants` | List / edit Azure AD tenant config + secret |
| `/sharepoint/drives` | List / edit registered SharePoint sites + drives |
| `/sharepoint/drives/{id}/mapping` | Per-drive column mapping editor |
| `/sharepoint/subscriptions` | Active webhook subscriptions, expiry countdown |
| `/sharepoint/events` | Inbound event log (filterable by status) |
| `/sharepoint/events/{id}` | Single event detail + raw payload + retry button |
| `/sharepoint/user-mappings` | AAD user → archive user mappings (Mode A) |
| `Admin > AHG Settings > SharePoint Integration` | Global toggles + retention map + webhook URL |

---

## Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `sharepoint:test-connection` fails with `HTTP 401 invalid_client` | Wrong client ID, wrong tenant ID, or the secret was not encrypted correctly on save. Re-enter the secret in the tenant edit page. |
| `sharepoint:test-connection` works but `GET /sites` returns empty | Admin consent not granted for `Sites.Read.All`, or the AAD tenant has site discovery restricted. Grant consent in the Azure portal. |
| Webhook subscription create returns `Subscription validation request failed` | The `Public webhook URL` (admin settings) is not reachable from Microsoft IP space, or returns non-200/text-plain on the validation handshake. The webhook URL must be HTTPS, public, and respond within 10 seconds. |
| Events stay at `queued` and never advance | Queue worker is not running. Start it: `php symfony queue:work --queue=integrations` (AtoM) or `php artisan queue:work --queue=integrations` (Heratio). |
| `skipped_not_allowlisted` for items that should ingest | The drive's `auto_ingest_labels` list does not include the item's `_ComplianceTag` value. Check at `/sharepoint/drives/<id>` and update the allowlist. |
| `skipped_duplicate` shows up after a Purview policy fires | Idempotency working as intended — the same (drive, item, etag) was already ingested. To force re-ingest, change the etag (edit the file content) or bump `_ComplianceTagWrittenTime`. |
| Files larger than 100 MB fail to download (AtoM target) | Phase 1 cap. Heratio target streams natively (no cap). Phase 2.B.1 adds streaming to AtoM. Workaround: configure smaller files. |
| Manual push returns `aad_user_not_mapped` | Auto-create is OFF and there is no row in `sharepoint_user_mapping` for this AAD user. Either enable auto-create in settings, or create a mapping row at `/sharepoint/user-mappings`. |
| Manual push returns `403 Files.Read.All` from Graph | OBO flow failed — the AAD app does not have *delegated* `Files.Read.All` granted, or the user has not consented to the SPFx app. Re-grant in Azure portal. |
| Subscriptions silently expired and stopped delivering | The renewal cron is not running. Check `/sharepoint/subscriptions` — any sub with `expires_at` in the past needs `sharepoint:subscribe` re-run. |

---

## Security notes

- **Client secret**: stored encrypted at rest via the framework `EncryptionService` (AtoM) or Laravel `Crypt` facade (Heratio). Never written to logs.
- **Webhook authentication**: the `clientState` echoed in every Graph notification is checked against the stored value (constant-time compare). Mismatches are dropped with HTTP 401 and never enqueued.
- **OBO flow**: manual push uses on-behalf-of so the user's actual SharePoint permissions are enforced. App-only credentials are never used to read SharePoint files for manual push.
- **Audit trail**: every successful ingest writes a row to the audit log with the source (`sharepoint_auto` or `sharepoint_push`), the SharePoint item id, the eTag, and the actor (the AAD user for Mode A; a service identity for Mode B).
- **Permission trimming on federated search**: gated to staff (editor/admin) roles only in v1, because the app-only Graph search returns un-trimmed results. OBO-based per-user trimming is a Phase 3.5 follow-on.

---

## Further reading

- Implementation plan: `atom-extensions-catalog/docs/technical/ahgSharePointPlugin_Implementation_Plan.md`
- AtoM plugin source: `atom-ahg-plugins/ahgSharePointPlugin/`
- Heratio package source: `heratio/packages/ahg-sharepoint/`
- SPFx package: `atom-extensions-catalog/spfx/atom-archive-push/`
- Microsoft Graph change-notifications reference: <https://learn.microsoft.com/graph/api/resources/change-notifications-api-overview>
