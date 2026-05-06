Work staged for Issue #79 — ICIP settings wiring (workspace changes, commit required)

What I added to the workspace (no commits yet):

- packages/ahg-icip/src/Services/LocalContextsHubService.php
  - Lightweight stub that returns results only if local_contexts_hub_enabled == 1 and local_contexts_api_key is present.

- packages/ahg-icip/src/Middleware/AuditIcipAccess.php
  - Middleware that inserts a lightweight row into `icip_access_log` and logs an info entry when `audit_all_icip_access` == 1.
  - Non-blocking: errors are caught and logged.

- packages/ahg-icip/src/Controllers/IcipController.php (updated)
  - Added helper getIcipSetting(key, default) for safe config reads.
  - Controller contains hooks and structure for the audit/default/gating behaviour (consultation defaults, notices, etc.).

- tests/Feature/IcipSettingsTest.php
  - Basic feature test scaffolding for follow-up default and audit middleware.

Outstanding work before this is fully live:

1) Migration: create `icip_access_log` table (middleware writes to this table). I did not add a migration — please create and run migration before enabling audit.

2) Middleware registration: AuditIcipAccess is present but not registered. Two options:
   - Add to the global/route middleware registration (App\Http\Kernel or package service provider), or
   - Apply it to specific ICIP routes (recommended: add to the ICIP object routes so only object views are audited).

3) Views: ensure forms/pages use the default_consultation_follow_up_days and require_acknowledgement_default where appropriate. I prepared controller helpers but left view edits minimal; you may want UX tweaks.

4) Local Contexts Hub: the service is a stub; real integration (HTTP client, auth header) requires an API spec and API key. Currently inert unless enabled.

5) Tests: the test scaffolding is present but needs more assertions and possibly DB fixtures.

Commit block (copy/paste locally)

```
# create a branch, stage the prepared files, commit and push
git checkout -b feat/icip/settings-wiring
git add packages/ahg-icip/src/Services/LocalContextsHubService.php \
    packages/ahg-icip/src/Middleware/AuditIcipAccess.php \
    packages/ahg-icip/src/Controllers/IcipController.php \
    tests/Feature/IcipSettingsTest.php

git commit -m "feat(icip): wire ICIP settings (audit, defaults, notices, LocalContextsHub stub) (#79)"

git push -u origin feat/icip/settings-wiring
```

Post-commit deploy steps (suggested):

```
composer dump-autoload
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
sudo systemctl reload php8.3-fpm
php artisan queue:restart
```

Smoke-test suggestions:
- Create a consultation and verify the follow_up_date is pre-filled when `default_consultation_follow_up_days` is set in `icip_config`.
- Enable audit (`audit_all_icip_access` = 1) and visit an object page (route guarded by AuditIcipAccess) — verify a row is in `icip_access_log`.
- Toggle `enable_public_notices` / `enable_staff_notices` and verify notices are shown/hidden as expected.

If you want, I can:
- Create the migration for `icip_access_log` and register the middleware in routes or Kernel; I will not run migrations or commits without your explicit instruction.
- Produce a unified patch instead of committing.
