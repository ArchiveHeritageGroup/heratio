# phpMyAdmin on Mogalakwena

phpMyAdmin is installed on the Mogalakwena Drupal VM and reverse-proxied
through this server's edge nginx so it is reachable on the public site.
URL: `https://www.mogalakwena.org/phpmyadmin/`. Two authentication layers
gate access: HTTP basic-auth at the edge, then phpMyAdmin's own
cookie-auth (which uses real MySQL credentials).

## Architecture

```
browser
  -> edge nginx (this server)
       location ^~ /phpmyadmin/        # before catch-all location /
         auth_basic ......(layer 1)
         proxy_pass ........... -> Mogalakwena VM
                                     nginx (snippets/phpmyadmin.conf)
                                       -> /usr/share/phpmyadmin
                                       -> php-fpm 8.2
                                       -> MySQL 8 (cookie-auth login = layer 2)
```

The mogalakwena edge vhost is the same one that proxies the rest of the
Drupal site; the phpMyAdmin block is inserted ahead of the catch-all
`location /` so it is matched first. Same proxy headers (`Host`,
`X-Forwarded-For`, `X-Forwarded-Proto`, etc.) and the
`Accept-Encoding ""` workaround for the multipurpose theme apply to the
phpMyAdmin path the same as everything else.

On the VM, an nginx snippet at `/etc/nginx/snippets/phpmyadmin.conf`
serves the phpMyAdmin tree from `/usr/share/phpmyadmin` using
`root /usr/share/`. The snippet has the standard
`try_files $uri $uri/ /phpmyadmin/index.php?$args` fallback,
a nested PHP location that includes `snippets/fastcgi-php.conf`, a
static-asset location with 7-day expiry, and an explicit deny on
`/phpmyadmin/(libraries|setup/(?:lib|frames))/` to keep internal trees
unreachable. The Drupal vhost includes the snippet on a single
`include snippets/phpmyadmin.conf;` line.

## Install method

phpMyAdmin was installed on the VM via the standard apt package
(`apt install phpmyadmin`) on Ubuntu 22.04 jammy. The dbconfig prompt
was answered with `dbconfig-no-thanks` because we do not want
phpMyAdmin to manage its own MySQL helper database in this deployment.
The web-server multiselect was left empty so apache2 is not pulled in;
the existing nginx + php8.2-fpm pipeline serves phpMyAdmin directly.

The blowfish_secret expected by `/etc/phpmyadmin/config.inc.php` lives
at `/var/lib/phpmyadmin/blowfish_secret.inc.php`. It is generated as a
48-character base64 string at install and is owned root:www-data with
mode 640. To rotate it, regenerate the file with the same shape; PHP
picks up the new secret on the next request, no nginx reload needed.

## Controluser ("pmadb") deliberately disabled

The package ships an `/etc/phpmyadmin/config-db.php` that defines a
`phpmyadmin` controluser used to connect to a `phpmyadmin` helper
database (the "pmadb") for storing bookmarks, query history, designer
metadata and other non-essential features. Because dbconfig was
declined, that MySQL user does not exist, and the default
config-db.php would otherwise produce
`Access denied for user 'phpmyadmin'@'localhost' (using password: YES)`
on every page load.

The fix used here is to blank the `$dbuser`, `$dbpass`, `$dbname`,
`$dbserver` and `$dbport` variables in `/etc/phpmyadmin/config-db.php`
(the original is preserved next to it as `config-db.php.bak`). With
those empty, phpMyAdmin skips the controluser connection entirely.
Browsing tables, running SQL, importing/exporting and managing users
all work normally; only pmadb-backed features (bookmarks, designer
saved layouts, persistent query history) are off.

To re-enable pmadb later: create a `phpmyadmin@localhost` MySQL user,
create the `phpmyadmin` database, run
`/usr/share/phpmyadmin/sql/create_tables.sql`, and restore the
original `config-db.php` (or fill in the relevant variables).

## Edge access posture

The edge `location ^~ /phpmyadmin/` block was first deployed with a
LAN allow-list (the LAN /24, the WireGuard /24, and loopback) plus
`deny all`, then opened up to the public internet on operator request
when access from outside the LAN was needed.

To compensate for the open posture, an HTTP basic-auth layer was added
at the edge: `auth_basic` plus a separate htpasswd file at
`/etc/nginx/.htpasswd-phpmyadmin` (root:www-data, mode 640). To rotate
or add basic-auth users, use `htpasswd` against that file and reload
nginx. The basic-auth user is presented before the request is even
proxied to the VM, so it acts as a cheap brute-force / scanner filter
in front of phpMyAdmin's own login.

The earlier LAN-only edge config is preserved as a timestamped
`.bak` next to `mogalakwena.theahg.co.za.conf` and can be restored if
a stricter posture is wanted (e.g. drop basic-auth and go back to
WireGuard-only).

## Login flow

1. Browser hits `https://www.mogalakwena.org/phpmyadmin/`.
2. Edge nginx returns 401 with a basic-auth challenge; user enters the
   basic-auth credentials maintained in `.htpasswd-phpmyadmin`.
3. Edge proxies the request to the VM, which serves phpMyAdmin's own
   login page.
4. User enters a real MySQL username + password; phpMyAdmin opens a
   cookie session against MySQL on the VM.

Either layer alone would gate the tool; running both means a single
leaked credential is not enough to reach a SQL prompt.

## Operator notes

- Backups of the edge vhost (`mogalakwena.theahg.co.za.conf.bak.*`)
  and of the VM phpMyAdmin config (`config-db.php.bak`) live next to
  the originals. Roll back by `cp` and reload nginx / refresh PHP.
- `nginx -t` is non-destructive on both ends and should be run before
  any `systemctl reload nginx`.
- The Drupal site continues to be served by the catch-all `location /`
  on the same vhost; nothing about its routing or theme behaviour
  changes when the phpMyAdmin location is added or removed.
- Static-asset 200s on `/phpmyadmin/themes/.../*.css` and a successful
  `/phpmyadmin/index.php` 200 with `<title>phpMyAdmin</title>` are the
  smoke-test signals after each reload.
