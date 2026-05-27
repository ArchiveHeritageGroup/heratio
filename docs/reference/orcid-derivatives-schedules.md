# Scheduled Jobs: RegenDerivatives + ORCID Sync

**Issue:** heratio#755
**Status:** Shipped in v1.109+.

## Background

Two scheduled batch jobs were missing from the cron registration:

1. **Derivative regeneration** - `RegenDerivativesCommand` existed but was not wired into `$schedule->command(...)`.
2. **ORCID sync** - No `ahg:orcid-sync` command existed at all. Researcher ORCID iDs were captured at registration but never re-validated against the ORCID public API.

## What shipped

### Derivative regeneration

Wired in `packages/ahg-core/src/Providers/AhgCoreServiceProvider.php` boot:

```php
$schedule->command('ahg:regen-derivatives --type=all')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping(120);
```

Runs Sundays at 02:00 SAST. `--type=all` regenerates thumbnails and reference copies for every digital object. Off-peak slot avoids competing with the nightly KBART (01:00) and library expiry batch (02:30/02:45).

### ORCID sync

New command `ahg:orcid-sync` in `packages/ahg-research/src/Console/Commands/OrcidSyncCommand.php`. Walks `actor` rows with a linked ORCID iD, calls the ORCID public API, updates the local record (or flags drift).

Wired in `AhgResearchServiceProvider::boot()`:

```php
$schedule->command('ahg:orcid-sync')
    ->dailyAt('01:30')
    ->withoutOverlapping(60);
```

Daily at 01:30, after the KBART refresh and before the library expiry batch. Short-circuits if `ORCID_CLIENT_ID` is not configured.

## Verification

```bash
sudo -u www-data php artisan schedule:list  # both entries appear
sudo -u www-data php artisan ahg:orcid-sync --dry-run
sudo -u www-data php artisan ahg:regen-derivatives --type=all --dry-run
```

## Notes

Both jobs respect their `ahg_settings` enable flags and short-circuit when disabled, so toggling them off from the admin UI is safe without code changes.
