# Project platform mapping

Two product lines, often confused under the AHG umbrella:

- **AtoM Heratio** is the legacy AHG fork of AtoM 2.x. Engine is Symfony 1.4, PHP 8.x, Propel ORM, AtoM-style templates with `decorate_with()`. The AHG plugin suite lives in `atom-ahg-plugins/` (e.g. `ahgLibraryPlugin`, `ahgThemeB5Plugin`, etc.). ES indices use the `atom_qubit*` prefix. Sometimes shortened to "AtoM-AHG" in path/code references and "Heratio" in customer-facing labels, but the engine is Symfony, not Laravel.

- **Heratio** is the standalone Laravel 12 rewrite. Canonical source at `/usr/share/nginx/heratio/`. Pure Laravel + Blade + Eloquent. Own DB (`heratio`), own ES indices (`heratio_*`), own storage roots. No Symfony, no Propel. Bumped through `bin/release` with `version.json` as the source of truth.

When the label "Heratio" appears by itself, it means the Laravel product. When prefixed "AtoM Heratio" or "AtoM-AHG", it means the legacy Symfony product.

## Per-project mapping (2026-05-13)

| Project | Platform | Notes |
|---|---|---|
| **WDB** (Women Developing Businesses Trust) | AtoM Heratio (Symfony 1.4 + AHG plugins) | Client production. Confirmed staying on AtoM-AHG; no platform migration to Laravel Heratio planned. |
| **ANC** (ANC archive) | Heratio (Laravel 12) | |
| **DAM** (digital asset management) | Heratio (Laravel 12) | |

## Why this matters when porting work

Code written for one platform does not drop into the other. A change that lands on Laravel Heratio (Eloquent model, Blade view, artisan command) needs a separate Symfony 1.4 port (Propel action, AtoM template partial, `symfony` task) before it can run on WDB or any other AtoM-AHG instance. The DB schemas are largely shared (AtoM base + a few `ahg_*` sidecars + Heratio extensions), so the migration target is mostly PHP/template code, not SQL.
