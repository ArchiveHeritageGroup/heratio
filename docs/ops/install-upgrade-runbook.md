# Heratio install and upgrade runbook

End to end, from bare host to a running instance, and from a running instance to the
next version. Written to be followed literally.

Two rules govern everything below.

**All development happens on `heratio-dev`.** Production instances are only ever
updated by pulling what was built and verified there. If you are asked to edit
application code directly on a live instance, say so and offer the dev-first path.

**Every push bumps a version.** Use `./bin/release`. Never `git push` application code
by hand.

---

## Part A - Fresh install

### A0. What the installer does not do

`bin/install` sets up the application. It does **not** configure the host around it.
On a fresh box the following are yours, and three of them will cause a working
install to fail in ways that look like application bugs:

| Item | Why it matters |
|---|---|
| php-fpm `ReadWritePaths` drop-in | **Mandatory.** See A5 - without it the app 500s on write. |
| Storage / NAS mounts | `HERATIO_STORAGE_PATH` must exist and be writable. |
| Cron | Scheduler, facet cache, backups. See A7. |
| Mail relay | The app's own mailer defaults to `log`. See A8. |
| TLS | `bin/install` renders plain HTTP nginx; add certbot afterwards. |

### A1. Prerequisites

PHP 8.3 with the usual Laravel extensions, MySQL 8, Node, Composer, git, curl.
Elasticsearch **7.17.x** (the docs elsewhere say 8; 7.17 is what runs). Stage 1 checks
all of it and will tell you what is missing before it changes anything.

### A2. Pick a scenario

Three, per `README.md`:

1. **Overlay onto an existing AtoM** - `bin/install-overlay`, see `docs/overlay-install-howto.md`
2. **Standalone clean install** - `bin/install`, the default and what follows below
3. **Docker test stack** - `docker/`, self-contained, nothing on the host

For a throwaway VM, `sudo bin/heratio-vm.sh` provisions Ubuntu 24.04 with the Docker
stack inside it and prints the IP.

### A3. Run the installer

```bash
git clone git@github.com:ArchiveHeritageGroup/heratio.git /usr/share/nginx/<instance>
cd /usr/share/nginx/<instance>
sudo bin/install --domain=example.org --admin-email=admin@example.org
```

Useful flags:

```bash
--non-interactive --admin-password='...'   # unattended
--sector=museum                            # opinionated theme + identifier mask
--sector=museum --with-sample              # + representative published records
--fresh                                    # DROP and recreate the database
--skip-es                                  # no Elasticsearch on this box
--skip-nginx                               # you manage nginx yourself
```

**It is idempotent.** Every stage detects completed work and skips it, so a failed run
is fixed by fixing the cause and running it again. It does not resume from a marker;
it re-checks.

### A4. The fourteen stages

| # | Stage | What it does |
|---|---|---|
| 1 | Preflight | Checks PHP/MySQL/Node/Composer/git/curl/ES. On a fresh MySQL, provisions a dedicated `heratio` DB user. |
| 2 | Composer | `composer install --no-dev`, skipped if `vendor/` is current. |
| 3 | NPM | `npm ci && npm run build`, skipped if `public/build/` exists. |
| 4 | .env | Copies `.env.example`, fills `APP_URL`/DB/`HERATIO_*`, runs `key:generate`. Skipped if `.env` exists. |
| 5 | DB create | `CREATE DATABASE`. `--fresh` drops first. |
| 6 | Core schema | Loads `database/core/0[0-3]_*.sql`. |
| 7 | Plugin schema | `php artisan heratio:install-bootstrap --pass=2`. |
| 8 | Seeds | Loads `database/seeds/0[0-7]_*.sql`. |
| 9 | Admin user | Creates the user row and puts it in admin ACL group 100. |
| 9b | Migrations | `php artisan migrate --force`, after seeds and admin. |
| 10 | Storage | Creates `HERATIO_UPLOADS_PATH` and `HERATIO_BACKUPS_PATH`. |
| 11 | Elasticsearch | `php artisan ahg:es-reindex --drop`. |
| 11b | Facet cache | `php artisan ahg:display-reindex` - the GLAM browse facets. |
| 12 | Nginx | Renders `config/nginx/heratio.conf.template`, links it, reloads. |
| 13 | Smoke test | `curl /`, expects 200/301/302. |
| 14 | Report | Prints URLs and admin credentials. |

### A5. The php-fpm drop-in - do this or the app will 500

The system unit ships `ProtectSystem=full`, which mounts `/usr` read-only **for the
php-fpm worker only**. Any Heratio under `/usr/share/nginx/` therefore cannot write
its own `storage/` from a web request.

The signature is confusing and worth recognising: the login GET often works, while
admin pages 500 with `Failed to open stream: Read-only file system` against
`storage/logs/laravel-*.log`. Cron-driven `artisan` runs are unaffected, because they
are forked from cron rather than the fpm unit - so daily logs write fine while the web
path fails, which sends people looking in the wrong place.

```ini
# /etc/systemd/system/php8.3-fpm.service.d/<instance>-storage.conf
[Service]
ReadWritePaths=/usr/share/nginx/<instance>/storage
ReadWritePaths=/usr/share/nginx/<instance>/bootstrap/cache
```

```bash
systemctl daemon-reload && systemctl restart php8.3-fpm
systemctl show php8.3-fpm | grep ReadWritePaths     # verify
```

Add an uploads path too if the instance serves files from under `/usr`.

Belt and braces, so a stray root-owned file cannot block the worker:

```bash
setfacl -d -m u:www-data:rwx storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
setfacl    -m u:www-data:rwx storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
```

### A6. Dedicated fpm pool (recommended for anything public)

Public instances get their own pool and socket rather than sharing `www`, so one
site's saturation cannot take the others down. See `docs/ops/fpm-pool-isolation.md`.
`bin/fpm-pool-monitor` runs from cron and alerts on saturation.

### A7. Cron

| Job | Schedule | Purpose |
|---|---|---|
| `heratio-schedule` | every minute | Laravel scheduler (`schedule:run`) |
| `ahg-facet-cache` | `0,4-23` hourly | GLAM browse facet cache |
| `mysql-full-dump` | **01:00** | Full host MySQL dump |
| `heratio-config-drift` | Mon 07:00 | `bin/check-config-drift --notify` |

The facet cache deliberately skips 01:00-03:00. It used to fire at 01:00:01 and rebuild
`display_facet_cache` in the same second the dump started, which killed the dump with
`Error 1412: Table definition has changed` at the same byte offset every night for
months.

Register new scheduled commands in `CronSchedulerService::getDefaultSchedules()` and
seed with `cron:seed`. A scheduled command must no-op cleanly when its integration is
unconfigured.

### A8. Mail

`email_setting.smtp_enabled` in the database **overrides** `.env MAIL_MAILER` at boot.
Read `config('mail.default')`, never `.env`, when working out what an instance will
actually do. Both instances currently resolve to `log`, meaning nothing is sent.

Host-level mail goes through msmtp (`/etc/msmtprc`). Use that for operational mail
rather than enabling app SMTP - a crawler once walked the admin forms and sent 14 real
emails, which is why dev's SMTP is off.

### A9. Verify the install

```bash
curl -sI https://<domain>/ | head -1                      # 200/301/302
sudo -u www-data php artisan about | head -20             # env, cache, DB
sudo -u www-data php artisan route:list | wc -l           # routes registered
mysql <db> -e "SELECT COUNT(*) FROM information_object;"
curl -s localhost:9200/_cat/indices/heratio_* | cut -c1-80
```

Log in as the admin the installer printed. Open a record, an admin settings page and a
browse page - the third catches a missing facet cache and the second catches A5.

---

## Part B - Upgrade an existing instance

### B1. Build and release on dev

```bash
cd /usr/share/nginx/heratio-dev
# edit, then:
git status --porcelain                 # nothing stray - bin/release stages with git add -A
./bin/check-locked                      # locked paths clean?
sudo -u www-data php artisan test --testsuite=Feature
sudo -u www-data ./bin/release patch "description" --issue NNN
```

If the change touches a locked path, unlock exactly the files you need first. Unlock is
one-shot and re-arms after a successful release:

```bash
sudo -u www-data ./bin/unlock <path> [<path> ...]
```

**Dev cannot push.** `origin` is HTTPS and the `www-data` deploy key is rejected, so
`bin/release` commits and tags then fails at the push. That is expected. Push with
root's key:

```bash
git push git@github.com:ArchiveHeritageGroup/heratio.git main
git push git@github.com:ArchiveHeritageGroup/heratio.git vX.Y.Z
```

Note that **everything in `bin/release` after `git push` is dead code on dev**, since
the push always fails. If a release step seems not to have run, this is why.

### B2. Deploy

Always `--check` first. It resolves the target and changes nothing:

```bash
sudo HERATIO_APP=/usr/share/nginx/<instance> /usr/local/sbin/heratio-deploy.sh --check
```

Read the output before continuing. It prints the app, instance, database, health URL,
lock file, and whether the demo baseline will be rebased. The script resolves its
target rather than hardcoding it, because a hardcoded path once had sasa's own copy
deploy the demo instead - wrong tree, wrong database dumped, and it printed "deploy
done".

```bash
sudo HERATIO_APP=/usr/share/nginx/<instance> /usr/local/sbin/heratio-deploy.sh
```

Its stages: **pre-deploy dump** (aborts if it cannot take a verified one) → fast-forward
pull → composer + assets → `migrate` + cache rebuild → restart queue workers → clear
compiled manifests + **restart** php-fpm → health check → rebase the demo baseline.

**Do not deploy during the 01:00 window.** The host MySQL dump runs then, and a deploy
that alters a table mid-dump kills it.

### B3. After the deploy

Conditional steps the deploy does not do:

| If you changed | Run |
|---|---|
| anything under `docs/help/` | `sudo -u www-data php artisan ahg:help-ingest-all --dir=docs/help` |
| ES mappings or analysers | `sudo -u www-data php artisan ahg:es-reindex --drop` |
| browse facets or display config | `sudo -u www-data php artisan ahg:display-reindex` |
| **demo data, after the deploy** | `/usr/local/sbin/heratio-demo-snapshot.sh` |

That last one matters and is easy to miss. The deploy rebases the baseline at the end
of its own run. Anything you change **afterwards** - seeding records, re-ingesting help
- is not in the baseline, and the 02:00 reset will discard it. Re-stamp, then confirm:

```bash
cat /mnt/nas/heratio/demo-baseline/heratio-demo.version   # must equal version.json
```

The reset refuses to run on a mismatch rather than rolling the schema back under newer
code. Safe, but it means a forgotten re-stamp stops the rotation silently.

### B4. Verify

```bash
bin/deploy-check                                          # catches pulled-code-without-composer
curl -sI https://<domain>/ | head -1
grep -c '' storage/logs/laravel-$(date +%F).log           # errors since deploy?
sudo -u www-data php artisan route:list --json | python3 -c 'import json,sys;print(len(json.load(sys.stdin)))'
```

Then open the actual feature in a browser. Two defects this year shipped complete,
passed every test, and were unreachable because nothing linked to the new route.

---

## Part C - Landmines

Each of these has cost real time at least once.

**Restart php-fpm, never reload.** The pool sets `opcache.validate_timestamps=0`, so a
reload keeps serving the old code. CLI checks pass against fresh code while the browser
runs the previous version, which is a very confusing hour.

**A new package's autoload line does not travel.** `bin/release` excludes
`composer.json` and `composer.lock` from every commit (`RELEASE_EXCLUDE`), because they
are permanently divergent per instance. So a new `ahg-*` package ships its code and
**not** its PSR-4 entry: the package registers nothing and its routes 404 with no error
anywhere. Add the line per instance, and guard the provider with `class_exists`. To
include a genuine dependency change, `INCLUDE_DRIFT=1 ./bin/release ...`.

**Per-instance files must be `skip-worktree`.** Because of the above, `composer.json`
and `composer.lock` diverge, and `heratio-deploy.sh` aborts on uncommitted tracked
changes. Set `git update-index --skip-worktree composer.json composer.lock` on each
instance. **Read the diff before clearing any such "stray" change** - discarding it
silently unregisters a package.

**Package migrations only run if the provider says so.** A package's migrations are
invisible to `artisan migrate` unless its ServiceProvider calls `loadMigrationsFrom()`.
Without it there is no error, just a table that never appears.

**A migration-created table referenced in a query breaks CI.** CI builds its test
database from `database/core/*.sql`, not from migrations. Any new table you then query
must be either mirrored into `database/core/*.sql` or guarded with `Schema::hasTable()`,
or CI 500s on a table that exists everywhere except there. The same applies to any
instance mid-deploy.

**Do not run `artisan` as root.** Laravel bootstrap can create the daily log file owned
by `root:root`, which blocks www-data until chowned. Always `sudo -u www-data php artisan`.

**Root-run git recovery leaves root-owned files.** If you fix a tree as root, `chown`
`version.json` and `.git` back to www-data, or the next `bin/release` cannot bump the
version and collides on a duplicate tag.

**Concurrent releases collide.** If another session tags the same number, fetch the SSH
remote, reset local to it, re-apply only your source files, and release as the next
number.

**Check `git status` before releasing.** `bin/release` stages with `git add -A`. A real
secret leaked this way once, via a stray `.env` backup.

---

## Part D - Rollback

**Database.** Every deploy takes a verified pre-deploy dump first, and refuses to
continue without one unless you pass `--no-backup`.

```bash
ls -t /mnt/nas/heratio/demo-baseline/pre-deploy-*.sql.gz | head
zcat pre-deploy-<ts>.sql.gz | mysql <db>
```

Both `pre-deploy-*` and `heratio-demo-*` are pruned after 14 days, by the script that
writes them - so the pruning only happens when that script runs.

**Code.**

```bash
cd /usr/share/nginx/<instance>
git fetch --tags && git checkout vX.Y.Z-1
composer install --no-dev && npm ci && npm run build
sudo -u www-data php artisan migrate --force        # only if the bad release migrated
rm -rf bootstrap/cache/*.php && systemctl restart php8.3-fpm
```

Migrations are not automatically reversible. Check the release's `down()` before
assuming a rollback is clean.

---

## Part E - Checklist

Fresh install:

- [ ] Prerequisites present, `bin/install` stage 1 clean
- [ ] `bin/install` completed all 14 stages
- [ ] **php-fpm `ReadWritePaths` drop-in added and verified**
- [ ] Dedicated fpm pool for a public instance
- [ ] Cron registered
- [ ] Storage paths exist and are writable by www-data
- [ ] Logged in; a record, an admin page and a browse page all render
- [ ] ES indices present and populated
- [ ] TLS

Upgrade:

- [ ] Built and verified on `heratio-dev`
- [ ] `git status` clean of strays; locked paths clear
- [ ] Feature suite passes
- [ ] Released, and both `main` and the tag pushed with root's key
- [ ] `--check` output read and correct
- [ ] Not the 01:00 window
- [ ] Deployed; health 200
- [ ] Conditional steps done (help / ES / facets)
- [ ] **Demo baseline re-stamped if anything changed after the deploy**
- [ ] Feature opened in a browser, not just tested

---

*Sources: `bin/install`, `bin/release`, `/usr/local/sbin/heratio-deploy.sh`,
`/usr/local/sbin/heratio-demo-{reset,snapshot}.sh`, `README.md`, `/etc/cron.d/*`,
`/etc/systemd/system/php8.3-fpm.service.d/*`. Verified against the running host on
2026-09-01 at v1.154.707.*
