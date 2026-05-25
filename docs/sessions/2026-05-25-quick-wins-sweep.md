# 2026-05-25 — Quick-wins sweep (v1.69.1 → v1.74.0)

## What shipped

Six Heratio releases on 2026-05-25, all targeting bounded Phase-1 work from the May-2026 audit issue sweep. Each release closed its origin Heratio issue + filed a PSIS-parity twin issue per the standing rule.

| Heratio version | Heratio issue closed | PSIS twin filed | What changed |
|---|---|---|---|
| **v1.69.1** | #673 | #704 | Registered `spectrum:overdue`, `sharepoint:auto-ingest`, `sharepoint:renew-subscriptions` in the `cron_schedule` registry via `CronSchedulerService::getDefaultSchedules()`. All three live on the live registry, `is_enabled=1`. |
| **v1.69.2** | #656 | #705 | WCAG fixes — restored `.form-item .form-control:focus` outline (3px theme-accent + 2px offset, was `outline: 0` — WCAG 2.4.7 violation), added `autocomplete` to 9 inputs across login/registration/password-reset (WCAG 1.3.5). Doc corrected. |
| **v1.70.0** | #672 | #706 | Queue worker daemon configs — supervisord program block + systemd template unit (with `ProtectSystem=full` hardening) + deploy doc. Configs ship; operator runs `systemctl enable --now` to activate. |
| **v1.71.0** | #660 | #707 | `rico:Occupation` entity — new `ric_occupation` table (with FK type `INT` matching base AtoM's `actor.id INT`), Eloquent model, admin CRUD UI at `/admin/ric/occupations`, structured `rico:hasOrHadOccupation` RDF emit in `RicSerializationService`, 6 OpenAPI endpoints. |
| **v1.72.0** | #666 | #708 | 3D viewer Phase 1+2 — animation toolbar (play/pause/scrub/time) hides when no animations; per-user camera bookmarks with new `object_3d_camera_bookmark` table + Save-view / Load-view UI; auth-gated writes, anonymous reads on published models. |
| **v1.72.1** | #674 | #709 | Email queue dispatch — 5 dispatch sites changed `->send()` → `->queue()`; 10 Mailables now `implements ShouldQueue`. **Critical follow-up:** `.env` still has `QUEUE_CONNECTION=sync` — code change is no-op until that flips + worker units (v1.70.0) start. |
| **v1.73.0** | #671 | (PSIS twin pending) | Backup notifications — replaced commented-out `mail()` stub with `BackupCompletedMail` + `BackupFailedMail` (`ShouldQueue`); Workbench JSON drop helper into `/var/spool/workbench/notifications/`. Settings: `backup_notify_workbench_username`, `backup_notify_on_success`, `backup_notify_on_failure`. |
| **v1.74.0** | #675 | (PSIS twin pending) | i18n Accept-Language fallback — added as step 4 in `SetLocale` resolution chain (URL → session → cookie → Accept-Language → default); response `Content-Language: <locale>` header; `<html lang="...">` + `dir="rtl|ltr"` (rtl for ar/he/fa/ur) in `master.blade.php`. Resolution still bounded by `setting.i18n_languages` whitelist. |

## Earlier in session (before this sweep)

| Version | Issue | Notes |
|---|---|---|
| v1.67.0 | #655 Phase 1 | OAI-PMH multi-format dissemination (oai_dc + oai_ead + oai_ead3 + mods + marcxml). Four new serializers in `ahg-metadata-export`. |
| v1.68.0 | #655 Phases 2-5 | OAI-PMH POST verb, `<friends>` container from federation_peer, `<deletedRecord>transient</deletedRecord>` + tombstones via `oai_deleted_record` table + `oai:mark-deleted` CLI, 120 req/min throttle, `/oai/docs` page. CSRF excepted for `/oai`. |
| v1.69.0 | #663 Phase 1 | MARC21/MARCXML standalone export — `Marc21BinaryEncoder` (MARCXML → ISO 2709 binary) + routes + 6 UI hook locations. |

## Critical follow-ups for operators

1. **#674 / v1.72.1 — Flip `.env`**: `QUEUE_CONNECTION=sync` → `database`. Then start the v1.70.0 systemd unit:
   ```bash
   sudo systemctl enable --now heratio-queue-worker@1.service heratio-queue-worker@2.service
   ```
   Until those two run, emails continue shipping synchronously (no harm, just no async benefit).

2. **#670 / v1.70.0 — Queue worker configs not auto-installed**: deliberate. Operator runs `sudo cp tools/systemd/heratio-queue-worker@.service /etc/systemd/system/`.

3. **#675 / v1.74.0 — Enable additional i18n languages in `setting.i18n_languages`** to let the Accept-Language fallback actually pick up `ar` / `he` / `fa` / `ur`. By default only `en` and `af` are enabled — the new fallback chain respects the existing whitelist.

## IIIF + Mirador sub-issues filed (#694-#701)

Earlier in the same session, the IIIF (#646) + Mirador (#647) umbrella audits were decomposed into 8 actionable sub-issues with concrete phased plans. They remain open for future work.

## New durable rules added

- **`feedback_always_file_psis_twin`** (established earlier same session) — every closed Heratio issue must have a matching PSIS-parity issue filed BEFORE close.
- **`feedback_always_update_km`** (NEW today) — every meaningful release / closed issue / non-trivial decision must triple-write: memory + `docs/sessions/...md` + (eventually) KM ingest endpoint.

## Cross-refs

- [Heratio releases](https://github.com/ArchiveHeritageGroup/heratio/releases)
- [Closed issues 2026-05-25](https://github.com/ArchiveHeritageGroup/heratio/issues?q=is%3Aclosed+closed%3A2026-05-25)
- PSIS twin issues: #704, #705, #706, #707, #708, #709 (and pending for #671, #675)
