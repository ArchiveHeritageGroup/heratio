This PR wires Spectrum / Collections settings so saved admin keys are actually consumed by the application (safe, additive changes).

What this PR implements
- Added SpectrumSettings helper (packages/ahg-spectrum/src/Services/SpectrumSettings.php) to read spectrum_* keys with defaults.
- Added EnsureSpectrumEnabled middleware (packages/ahg-spectrum/src/Middleware/EnsureSpectrumEnabled.php) — gates spectrum routes when `spectrum_enabled` is false.
- Registered middleware on the spectrum route group (packages/ahg-spectrum/routes/web.php).
- Small controller hook in SpectrumController to use SpectrumSettings::isEnabled() and to expose helper methods for defaults.
- Test scaffold (tests/Feature/SpectrumSettingsTest.php).
- README note (packages/ahg-spectrum/README.md).

What remains (outstanding tasks)
1. Prefill defaults in forms (valuation/loan/insurance) — controller helpers are present; view tweaks may be required for UX.
2. Add server-side validators for spectrum_require_photos, spectrum_require_valuation, spectrum_require_insurance on relevant create/update flows.
3. Hook notification/reminder settings (spectrum_valuation_reminder_days, spectrum_condition_check_interval) into SpectrumNotificationService to enqueue reminders (scaffolded integration points present).
4. Wire auto-create movement and barcode toggles (deferred/optional).
5. Add/expand unit & feature tests and run CI.

How to test (smoke test checklist)
1. Push branch and clear caches:
   composer dump-autoload
   php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
   sudo systemctl reload php8.3-fpm
2. Toggle master switch off (DB) and confirm spectrum routes return 404 and menu links hidden.
3. Set spectrum_default_currency and spectrum_loan_default_period and verify create forms prefill values (view tweaks may need enabling).
4. Run tests: vendor/bin/phpunit --filter SpectrumSettingsTest

Notes
- Changes are additive and non-destructive. No DB migrations were required.
- This PR focuses on wiring the settings to the codebase; follow-up PRs will implement full validators, notification scheduling, and auto-movement as desired.

Ready for review and merge.