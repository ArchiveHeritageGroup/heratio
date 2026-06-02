# /opt/ahg-ai services: database credentials & rotation

Summary: the Python/Flask services under `/opt/ahg-ai` (e.g. the RiC reference
site that backs Heratio's `/ric-api/*` proxy) connect to the local MySQL `archive`
database. The credential is read from an environment variable supplied by a
systemd drop-in, not hardcoded. This note records that setup so a MySQL password
rotation is a known, no-code-edit procedure - a stale hardcoded password here
previously caused production 500s on `/ric-api/stats` and `/ric-api/relations/types`.

## Background

`/opt/ahg-ai` is a fragile, loosely-tracked repo. The RiC site service
(`ahg-ric-site.service`, `/opt/ahg-ai/ric-site/app.py`, port 5055) queries the
AtoM `archive` MySQL database for RiC statistics and name resolution. Heratio's
nginx proxies `https://heratio.theahg.co.za/ric-api/*` to this Flask service.

Originally the DB password was hardcoded in `app.py` (three string literals, two
of them the DB credential and one an unrelated demo-user hash). When the MySQL
root password was rotated, the hardcoded value went stale and every DB-backed
RiC endpoint returned 500 ("Access denied for user 'root'"). The image/PDF and
Laravel-native RiC routes were unaffected, which masked it.

## How it works now

`app.py` reads the connection from the environment, with safe fallbacks, near
the top of the file:

    ARCHIVE_DB_HOST     = os.environ.get('ARCHIVE_DB_HOST', 'localhost')
    ARCHIVE_DB_USER     = os.environ.get('ARCHIVE_DB_USER', 'root')
    ARCHIVE_DB_NAME     = os.environ.get('ARCHIVE_DB_NAME', 'archive')
    ARCHIVE_DB_SOCKET   = os.environ.get('ARCHIVE_DB_SOCKET', '/var/run/mysqld/mysqld.sock')
    ARCHIVE_DB_PASSWORD = os.environ.get('ARCHIVE_DB_PASSWORD', <fallback>)

Both `pymysql.connect(...)` calls use these variables (no inline password).

The password value is provided by a root-only systemd drop-in (NOT committed to
any repo):

    /etc/systemd/system/ahg-ric-site.service.d/db.conf
    -------------------------------------------------
    [Service]
    Environment=ARCHIVE_DB_PASSWORD=<the current MySQL password>

File permissions are 600 (root only). The password is the same one Heratio's
Laravel app uses (its `.env` `DB_PASSWORD`), since both reach the same MySQL
instance.

## Rotation procedure (when the MySQL password changes)

No code edit is required. Update the drop-in and restart:

    sudo nano /etc/systemd/system/ahg-ric-site.service.d/db.conf   # set new value
    sudo chmod 600 /etc/systemd/system/ahg-ric-site.service.d/db.conf
    sudo systemctl daemon-reload
    sudo systemctl restart ahg-ric-site.service

Verify:

    curl -s -o /dev/null -w '%{http_code}\n' https://heratio.theahg.co.za/ric-api/stats
    # expect 200; the response body should carry entity_count / relation_count

Keep the new value in sync with Heratio's `.env` `DB_PASSWORD` (same MySQL).

## Scope / other services

As of this note, the RiC site is the only `/opt/ahg-ai` service found connecting
to MySQL with the previously-hardcoded credential. If another `/opt/ahg-ai`
service starts returning DB-auth 500s after a rotation, check it the same way:

    grep -rl "pymysql.connect\|mysql.connector\|MySQLdb" /opt/ahg-ai --include=*.py

and give it the same env-var treatment + a per-service systemd drop-in rather
than hardcoding.

## Notes

- This is server/runtime config (systemd drop-in + an `/opt/ahg-ai` file); it is
  not in the Heratio git repo, so a Heratio release does not deploy or restore
  it. Re-apply if the host is re-provisioned.
- Do not edit `/opt/ahg-ai` with destructive git operations; it is fragile.
  Targeted file edits + a service restart are the safe pattern.
- A leftover demo-user password hash elsewhere in `app.py` is unrelated to the DB
  credential and must not be changed during a DB rotation.
