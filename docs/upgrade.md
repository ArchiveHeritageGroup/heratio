# Upgrading Heratio

Every Heratio release is a git tag (`vX.Y.Z`) and, for Docker, an image with the same tag. Upgrading means moving to a newer tag and letting the schema catch up. Schema changes are additive and guarded, so an upgrade adds tables and columns but never drops your data. Releases are listed at <https://github.com/ArchiveHeritageGroup/heratio/releases>; read the notes for every release between yours and the target before you start.

## 1. Back up first

Take a database dump and a copy of the uploads before any upgrade. Nothing in the upgrade deletes data, but a backup is the only way back if a step fails half-way.

```bash
mysqldump --single-transaction --routines --triggers heratio > heratio-before-upgrade.sql
tar -czf uploads-before-upgrade.tar.gz -C "$(dirname "$HERATIO_UPLOADS_PATH")" "$(basename "$HERATIO_UPLOADS_PATH")"
```

`HERATIO_UPLOADS_PATH` is in your `.env`. In Docker, dump through the MySQL container (`docker compose exec mysql mysqldump ...`) and copy the `heratio-storage` volume.

## 2. Standalone or overlay install (git)

```bash
cd /usr/share/nginx/heratio
sudo -u www-data git fetch --tags
sudo -u www-data git checkout vX.Y.Z        # the release you are moving to
sudo bin/install                            # idempotent: composer, assets, schema, seeds
sudo -u www-data php artisan migrate --force
sudo -u www-data ./bin/deploy-check         # vendor matches composer.lock, no high advisories, app boots
```

`bin/install` on an existing install leaves `.env`, the admin user and the nginx vhost alone, re-runs the package schema pass and re-applies seeds without duplicating rows. Section 12 of [standalone-install-howto.md](standalone-install-howto.md#12-updating) lists exactly what it skips.

If you track `main` rather than tags, `git pull --ff-only` does the same job. The `hooks/post-merge` hook runs the migrations for you after a pull. A `git checkout` of a tag does not fire that hook, so run `migrate` yourself as shown.

An overlay install over an AtoM database upgrades the same way. Heratio only adds its own tables and columns, so AtoM data is untouched.

## 3. Docker

Set the version you want in `.env.docker` and restart the stack:

```bash
cd heratio
sed -i 's/^HERATIO_VERSION=.*/HERATIO_VERSION=vX.Y.Z/' .env.docker   # add the line if it is not there
docker compose --env-file .env.docker pull
docker compose --env-file .env.docker up -d
docker compose logs -f heratio
```

On every start the container runs the package schema pass and `migrate`, then rebuilds the hierarchy tables, so a new image brings the database up to date by itself. Data lives in the named volumes (`heratio-mysql`, `heratio-storage`, `heratio-es`) and survives the image swap. Pin a version rather than running `latest`, so that a restart never upgrades you by surprise.

## 4. After the upgrade

- Sign in as an administrator and open a few records, the browse page and the admin dashboard.
- If search results look stale, rebuild the index: `php artisan ahg:es-reindex`.
- The version in the page footer and in `version.json` should show the new release.

## Going back

Restore the database dump and uploads you took in step 1, then check out the previous tag (or set the previous image tag) and run step 2 or 3 again. Running an older release over a newer schema without restoring is not supported.
