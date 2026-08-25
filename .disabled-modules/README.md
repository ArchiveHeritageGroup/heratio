# Per-instance module switches

Drop a file named after a module here to turn it OFF on **this instance only**:

```bash
touch .disabled-modules/archaeology
sudo -u www-data php artisan optimize:clear
sudo systemctl restart php8.3-fpm      # the pool runs opcache.validate_timestamps=0
```

Remove the file and repeat to turn it back on. Nothing else is needed - do not
edit `composer.json` or `bootstrap/providers.php` to switch a module off.

Everything in this directory except this README is gitignored, so the switch is
per-instance and survives the `git pull --ff-only` that every deploy runs.

## Recognised markers

| marker          | effect                                                      |
|-----------------|-------------------------------------------------------------|
| `archaeology`   | `ahg-archaeology` - site / context / finds catalogue         |
| `harris-matrix` | `ahg-harris-matrix` - stratigraphic analysis                  |

Disabling `archaeology` also disables `harris-matrix` automatically - it reads
the archaeology tables and mounts its routes under `/archaeology`, so it has
nothing to stand on once the base module is off. You do not need both markers.

The list is `bootstrap/providers.php`; add the `$moduleOff('...')` guard there
to make a new module switchable.

## What this does NOT do

Switching a module off removes its routes and provider. It does **not** drop its
tables or delete its data - the rows stay where they are and come back if the
module is switched on again. Check the module's tables are empty before
assuming nobody is using it.

## Current state (2026-08-25)

| instance    | archaeology | harris-matrix |
|-------------|-------------|---------------|
| heratio-dev | on          | on            |
| heratio     | on          | not installed |
| sasa        | **off**     | not installed |

"Not installed" means the PSR-4 autoload entry has never been applied on that
instance, which `class_exists()` handles separately from a marker file.
