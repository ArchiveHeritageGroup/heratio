# Heratio Docker test stack - how a fresh install works (and what broke it)

`docker compose -f docker/docker-compose.yml --env-file docker/.env.docker up -d --build`
brings up a complete throwaway Heratio (PHP 8.3 + nginx + MySQL 8 + Elasticsearch 8)
on `http://<host>:8088/`. First boot runs `docker/init.sh` (idempotent): generate
`.env` + key, load `database/core/*.sql`, run `heratio:install-bootstrap` passes 1/2,
load `database/seeds/*.sql`, create the admin user, build ES indices, warm caches.

A from-scratch `up --build` must end with every page returning 200 and the admin
able to log in. The image is ~4 GB (NOT 26 GB - see bloat note).

## Things that broke it (all fixed June 2026) - check these first if it regresses

1. **Image was 25.9 GB.** `.dockerignore` did not exclude the local data dirs that
   `COPY . .` then dragged in: top-level `./uploads` (16 GB of sample objects),
   `./FamilySearch` (4 GB), `./venv`, `./stuff`, `./site`, `./audit_outputs`,
   `./docs`, plus `storage/logs` (1.4 GB) and `storage/app`. Excluding them dropped
   the image to ~4 GB. Keep these in `.dockerignore`.

2. **`.dockerignore` `**/models/` stripped real source.** It matched
   `packages/ahg-ai-compliance/resources/views/models/*.blade.php` (lowercase
   `models`), so that admin page 500'd. Removed. Model WEIGHTS are still caught by
   the extension globs (`*.safetensors` etc.) + the Dockerfile AI-leak guard. NB
   Docker ignore matching is case-sensitive, so `app/Models/` (capital) was safe.

3. **`/` 500: `Table 'heratio.sessions' doesn't exist`.** `init.sh` hardcoded
   `SESSION_DRIVER=database` / `CACHE_STORE=database`, but the schema has no Laravel
   `sessions`/`cache` tables (and no migration creates `sessions`). Production `.env`
   uses `file` for both - init.sh now does too (`file`/`file`, `QUEUE_CONNECTION=sync`).

4. **`/glam/browse` 500: `Unknown column 'library_item.condition_grade'`.** The
   `database/core/*.sql` dump had drifted from the code (library_item alone was
   missing 22 columns). Fix: `database/core/00_core_schema.sql` is now a **full
   structure dump regenerated from the authoritative production DB** (1114 tables,
   DEFINERs stripped, `CREATE TABLE IF NOT EXISTS`, `AUTO_INCREMENT=` counters
   stripped). The old split files `01/02/03_*.sql` are intentionally emptied
   (consolidated into 00). Regenerate with:
   `mysqldump --no-data --skip-triggers --no-tablespaces --routines=FALSE --events=FALSE heratio`
   then `sed` out `DEFINER=...`, force `IF NOT EXISTS`, wrap in `SET FOREIGN_KEY_CHECKS=0/1`.

5. **Only 837/1114 tables loaded -> cascade of 500s + ES reindex failure.** The
   schema-load line was `mysql ... | head -5` with no `--force`: one statement error
   (e.g. a type-drifted FK = MySQL error 3780) aborted the entire load, and the pipe
   to `head` can SIGPIPE-kill mysql mid-load. `init.sh` now loads with `--force` and
   writes errors to a file (no head pipe). The 3780 FK warnings are harmless (the
   dump has a few live FKs whose columns drifted out of type-compatibility; with
   `--force` all tables still create). ES reindex failing at init was a *downstream*
   effect of the incomplete schema, not an ES problem.

6. **`/informationobject/browse` redirect dropped the port (404).** The container's
   nginx listens on `:80`; the host maps `8088->80`, so Laravel built absolute
   redirects as `http://localhost/...` (no `:8088`). Fix: `FORCE_ROOT_URL=true`
   (set in compose + init.sh `.env`) makes `AppServiceProvider::boot()` call
   `URL::forceRootUrl(config('app.url'))`. The flag is unset on metal/production,
   so the change is inert there.

7. **php-fpm pool collision.** The pool was named `[heratio]` but the base image
   already defines `[www]` on `127.0.0.1:9000` ("Address already in use"). Renamed
   our pool to `[www]` so it overrides rather than duplicates.

## Known good result

1130 tables auto-loaded, bootstrap completes with no ES error, `/`, `/login`,
`/glam/browse`, `/actor/browse`, `/repository/browse`, `/informationobject/browse`,
`/research` all 200, admin login (POST `/login`) returns 302. Image ~4.13 GB.

Further size trimming (multi-stage build / prune `node_modules` after `npm run build`)
is possible but not required for a working stack.
