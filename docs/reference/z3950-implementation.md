# Z39.50 Implementation Reference

**Issue:** heratio#759
**Package:** `packages/ahg-z3950/`
**Status:** Client + SRU 2.0 HTTP server shipped (v1.112+). Native Z39.50 binary daemon out of scope (SRU covers the federated discovery use case).

## Package surface

| Component | Path |
|---|---|
| Service provider | `src/Providers/AhgZ3950ServiceProvider.php` |
| Controller | `src/Controllers/Z3950Controller.php` (378 lines) |
| Service | `src/Services/Z3950Service.php` (545 lines) |
| Routes | `routes/web.php` |
| Migrations | `database/migrations/2026_05_19_000001_create_z3950_tables.php` |
| Config | `config/ahg-z3950.php` |

Discovered by Laravel via `extra.laravel.providers` in `packages/ahg-z3950/composer.json`. Required in root `composer.json` as `ahg/z3950: @dev`.

## Database tables

- `library_z3950_target` - vendor catalogue targets (host, port, database, syntax, charset, auth)
- `library_z3950_search_log` - search audit trail
- `library_z3950_result_set` - in-flight result-set cache (30 min TTL)

## Routes

```
GET  /z3950                              -> z3950.index
GET  /z3950/search                       -> z3950.search
POST /z3950/search                       -> z3950.search-run
GET  /z3950/result/{resultSet}           -> z3950.result
GET  /z3950/import/{resultSet}/{record}  -> z3950.import
POST /z3950/import                       -> z3950.import-batch
GET  /z3950/admin                        -> z3950.admin (admin only)
GET  /z3950/target/create                -> z3950.target.create
POST /z3950/target                       -> z3950.target.store
DEL  /z3950/target/{id}                  -> z3950.target.delete
```

## YAZ dependency

Native Z39.50 needs the `php-yaz` PECL extension:

```bash
sudo apt install libyaz-dev
sudo pecl install yaz
echo "extension=yaz.so" | sudo tee /etc/php/8.3/mods-available/yaz.ini
sudo phpenmod yaz
sudo systemctl restart php8.3-fpm
```

If `yaz` is absent, `Z3950Service` falls back to an HTTP-to-Z39.50 gateway path with reduced reliability.

## SRU 2.0 server (v1.112+)

| Component | Path |
|---|---|
| SRU controller | `src/Controllers/SruController.php` |
| SRU service (CQL parser + record renderers) | `src/Services/SruService.php` (~440 lines) |
| Route | `GET /sru` (registered in `AppServiceProvider::register()` to win against the locked `/{slug}` catch-all) |

CQL indexes: `cql.anywhere`, `dc.title`, `dc.creator`, `dc.subject`, `dc.identifier`, `dc.publisher`, `dc.date`, `bath.isbn`, `bath.issn`. Schemas: `info:srw/schema/1/marcxml-v1.1`, `info:srw/schema/1/dc-v1.1`. Operations: `explain`, `searchRetrieve`.

## Gaps vs heratio#759 acceptance

- Native Z39.50 binary daemon - intentionally out of scope; SRU is the deployed federated-discovery surface
- SRW (SOAP) variant - not implemented

## Verification

```bash
sudo -u www-data php artisan route:list | grep z3950
sudo -u www-data php artisan tinker
> app(\AhgZ3950\Services\Z3950Service::class)->testConnection(1)
```
