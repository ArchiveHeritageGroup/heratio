feat(icip): wire ICIP settings (audit, defaults, notices, LocalContextsHub stub) (#79)

This PR wires several ICIP admin settings into the Heratio codebase. It is an additive, non-destructive change that implements a first-pass of runtime behaviour and test scaffolding. The goal is to allow the admin-configured settings to take effect and enable later polishing.

What this PR adds
- packages/ahg-icip/src/Services/LocalContextsHubService.php
  - Lightweight stub for Local Contexts Hub integration. Returns empty results unless enabled and an API key is present.

- packages/ahg-icip/src/Middleware/AuditIcipAccess.php
  - Middleware that creates a lightweight access record and logs an info entry when `audit_all_icip_access` == 1.
  - Writes to `icip_access_log` (migration included). Errors are caught and logged to avoid breaking the site.

- packages/ahg-icip/src/Controllers/IcipController.php
  - Added `getIcipSetting()` helper for safe config reads and inserted hooks to apply audit logging, default follow-up pre-fill, and gating for notices.

- database/migrations/2026_05_07_000000_create_icip_access_log_table.php
  - Migration to create `icip_access_log` table (object_id, user_id, route, ip_address, user_agent, meta, timestamps).

- tests/Feature/IcipSettingsTest.php
  - Feature test scaffold (requires fixtures and further assertions).

Notes / outstanding work (to complete after merge)
1. Middleware registration: AuditIcipAccess must be applied to ICIP object view routes (or registered in Kernel) so it runs on object pages.
2. Run DB migration before enabling audit: `php artisan migrate`.
3. Views: small template updates remain to wire defaults and gating:
   - Use `default_consultation_follow_up_days` to pre-fill `follow_up_date` in consult-create.
   - Apply `require_acknowledgement_default` checkbox default.
   - Gate public/staff notices with `enable_public_notices` / `enable_staff_notices`.
4. Enforce `require_community_consent` at publish/visibility transitions.
5. Flesh out tests in `tests/Feature/IcipSettingsTest.php`.
6. Optional: implement Local Contexts Hub HTTP integration (requires API spec & key).

Smoke test / deployment steps (after merge):
- composer dump-autoload
- php artisan migrate
- php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
- sudo systemctl reload php8.3-fpm
- php artisan queue:restart

Smoke test checklist:
- Set `default_consultation_follow_up_days` in `icip_config` and create a consultation → confirm follow_up_date is prefilled.
- Enable `audit_all_icip_access` = 1 and visit an ICIP object → confirm row in `icip_access_log`.
- Toggle `enable_public_notices` / `enable_staff_notices` and verify notices appear/disappear.

This PR is intentionally conservative: no fields removed/renamed; the LocalContextsHubService is a safe stub by default.

Issue: closes none yet — this is a work-in-progress for #79 and should not close the issue until runtime verification and polish are complete.

Reviewer notes:
- Please run migrations and the smoke tests above before enabling audit in production.
- I recommend additional tests and a follow-up PR to fully wire views and publish-time consent enforcement.
