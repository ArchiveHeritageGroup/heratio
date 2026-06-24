# Researcher download / storage quotas (#1325)

Adds per-researcher quota enforcement to the research portal, plus the workspace
file-upload subsystem the storage cap enforces on.

## Quota model

- `research_quota_policy` - configurable limits, resolved **most-specific-first
  per metric**: user > project > role > global. A partial override (e.g. a
  per-user storage bump) inherits the other limits from the broader policy.
  Columns: scope (global/role/user/project), scope_key, period (monthly/total),
  max_downloads, max_storage_bytes, soft_warn_pct, is_active. Enumerated values
  (`quota_scope`, `quota_period`) come from the Dropdown Manager. A global
  default (100 downloads/month, 5 GiB storage) is seeded.
- `ResearchQuotaService` - `getEffectiveLimits()`, `currentDownloadUsage()`
  (counts `research_activity_log` rows with activity_type='download' in the
  period), `currentStorageUsage()` (SUM of `research_workspace_file.file_size`),
  `checkDownload()` / `checkStorage()` (return allowed/warn/usage/limit/pct/
  message - hard-block at limit, soft-warn at `soft_warn_pct`%), `usageReport()`
  for the admin dashboard.

## Workspace files (new upload path)

`research_workspace_file` + `ResearchWorkspaceFileController`
(`/research/workspaces/{id}/files`): list / upload / download / delete, scoped
to workspace owners and accepted members. Files are stored under
`config('heratio.storage_path').'/research/workspace/{id}/'` (sanitised name,
sha256). **Upload is storage-quota enforced** (hard-block over limit, soft-warn
flash near it); **download is download-quota enforced** and logged so it counts.
Every mutation also writes human-action provenance via `LogsResearchActivity`.

## Admin

`ResearchQuotaController` + `research/admin-quotas` view (`/research/quotas`,
admin-only): a usage-vs-limit table (per researcher, colour-graded progress
bars, "unlimited" when null) and full CRUD over `research_quota_policy`
(scope/period from the Dropdown Manager).

## Enforcement scope / follow-up

- Enforced now: workspace-file **upload** (storage) and **download** (count).
- The legacy reproduction-file serving path (`ReproductionService::getFileByToken`
  /`recordDownload`) is **not wired anywhere in the codebase**, so there is no
  live reproduction download to gate today.
- Broadening the download guard to the other real download surfaces (project
  export ZIP / single-format, replication pack) is a clean follow-up using the
  same `ResearchQuotaService::checkDownload()` + `logDownload()` calls.
