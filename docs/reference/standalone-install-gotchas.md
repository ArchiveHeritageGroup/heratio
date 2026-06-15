# Heratio standalone install - common gotchas (fresh Ubuntu 24.04 / cloud server)

Quick answers for the issues that surface on a fresh `bin/install` of standalone
Heratio (Laravel 12) on a clean Ubuntu 24.04 box. Verified on a fresh VM install,
2026-06-15. Installer hardened in v1.142.167.

## The login page is `/login`, NOT `/admin/login`

There is **no `/admin/login` route** in Heratio - there never has been.
Authentication is mounted at the framework-standard path:

- Correct login URL: `http://<your-server>/login`
- `/user/login` also works (302-redirects to `/login`)
- `/admin/*` pages are gated *behind* auth, but the login form itself is at `/login`

If `php artisan route:list` shows no `admin/login` between `admin/jobs/*` and
`admin/library/*`, that is expected and correct. Hitting `/admin/login` returns
404 on every version (checked v1.142.20 and v1.142.120+).

Older installer builds printed `https://.../admin/login` in the final report -
that string was wrong (fixed in v1.142.167, which now prints `http://.../login`).
Just browse to `/login` and sign in with the admin email + password. No reinstall.

## HTTP 500 on `/` right after install = storage owned by root

`bin/install` runs as root. A freshly-cloned `storage/` and `bootstrap/cache`
are then owned `root:root`, but php-fpm runs as `www-data`, so the app cannot
write logs / compiled views / framework cache and every web request returns 500
(the Laravel log is empty because www-data cannot even create it).

Check:
```
ls -ld storage storage/logs bootstrap/cache
```
If those are `root:root`, fix:
```
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```
v1.142.167 does this automatically in Stage 10, so new installs no longer hit it.

## The vhost is HTTP-only (port 80)

The shipped nginx template (`config/nginx/heratio.conf.template`) listens on
`:80` only - there is no 443 / TLS block by default (a comment marks where to add
one). So:
- The site is served over `http://`, not `https://`.
- A smoke test or health check that curls `https://` will get a false failure;
  use `http://`. (v1.142.167 fixed the installer's own smoke test accordingly.)
- `APP_URL` in `.env` defaults to `https://<domain>` - if you front the box with
  a TLS-terminating proxy that is correct; for a bare http-only box you may want
  to set `APP_URL=http://<domain>` to avoid mixed-scheme URL generation.

## Empty Browse menu / bare Plugin Management = base plugins not registered

The nav Browse dropdown and `/admin/ahgSettings/plugins` are driven by the
`atom_plugin` registry (`is_enabled=1` rows). `atom_plugin.is_enabled` controls
nav/menu visibility, NOT route loading (Laravel ServiceProviders load routes
regardless). A fresh install used to register only ~3 plugins, so Browse was
empty and Plugin Management nearly so.

The base set is the seven `is_core=1` plugins, always installed AND enabled from
the start: `ahgCorePlugin`, `sfPropelPlugin`, `qbAclPlugin`, `sfPluginAdminPlugin`,
`ahgSettingsPlugin`, `ahgSecurityClearancePlugin`, `ahgThemeB5Plugin`. Three of
them (`sfPropelPlugin`, `qbAclPlugin`, `sfPluginAdminPlugin`) are legacy registry
names with no self-registering package, so they can only come from a seed -
`database/seeds/08_base_plugins.sql`. Every other plugin is registered/enabled
one at a time by the admin via `/admin/ahgSettings/plugins`.

If Browse is empty on an existing install, confirm the base set:
`SELECT name,is_enabled FROM atom_plugin WHERE is_core=1;` - all seven should be 1.

## Admin can log in but every /admin page is 403

Admin authorisation is `AclService::canAdmin()`, which needs the user in the
`administrator` ACL group (`acl_group.id=100`) via `acl_user_group`. The group
list is cached 300s (`Cache::remember("acl_groups_{id}")`), so after changing a
user's groups run `php artisan cache:clear` or wait out the TTL. The installer
(v1.142.172+) grants group 100 to the admin it creates.

## Fresh MySQL 8 root is socket-only

On a clean Ubuntu 24.04 MySQL 8 install, `root` authenticates via `auth_socket`
(no TCP password). The app connects over TCP as a user+password, so grant one:
```
sudo mysql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<pw>';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '<pw>';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```
Then pass `--db-pass=<pw>` to `bin/install` (or use a dedicated app DB user).

## Fresh Elasticsearch 8 ships with TLS + auth ON

Heratio expects plain `http://localhost:9200`. A default ES 8 install enables
security (HTTPS + a generated password) and will refuse plain HTTP. For a
single-node dev/standalone box, set `/etc/elasticsearch/elasticsearch.yml` to:
```
network.host: 127.0.0.1
http.port: 9200
discovery.type: single-node
xpack.security.enabled: false
xpack.security.http.ssl.enabled: false
xpack.security.transport.ssl.enabled: false
```
Do not leave a duplicate `xpack.security.enabled` key in the file - ES fails to
start with `Duplicate field 'xpack.security.enabled'`. Then
`sudo systemctl restart elasticsearch` and confirm `curl http://localhost:9200`
returns JSON. (For a production box exposed beyond localhost, keep security on
and configure Heratio's ES credentials instead.)
