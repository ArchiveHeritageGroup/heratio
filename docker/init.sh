#!/bin/bash
# Heratio first-boot bootstrap inside the container. Idempotent - safe to
# run on every restart. Mirrors bin/install stages 4-13 but skipped where
# the compose stack already provides the prerequisite (we don't manage MySQL
# or nginx here, those run in their own services / via supervisord).
#
# Stages run:
#   4.  .env + APP_KEY        (only on first boot, when .env is absent)
#  6-7. core schema + plugin install.sql passes
#   8.  seeds (taxonomies, settings, menus, ACL, static pages, eras)
#   9.  admin user
#  11.  Elasticsearch index create / clone
#
# A marker file at storage/.heratio-installed signals "schema already loaded";
# subsequent boots only run plugin-install pass-2 (idempotent) + ES check.

set -uo pipefail
cd /var/www/heratio

MARKER=storage/.heratio-installed

mysql_run() {
    mysql --protocol=TCP \
          -h "${DB_HOST:-mysql}" \
          -u "${DB_USERNAME:-heratio}" \
          -p"${DB_PASSWORD:-heratio-test}" \
          "${DB_DATABASE:-heratio}" "$@"
}

# ─── Stage 4: .env ────────────────────────────────────────────────────────────
if [ ! -f .env ]; then
    echo "[init] generating .env from compose env-vars"
    cat > .env <<EOF
APP_NAME="Heratio"
APP_ENV=${APP_ENV:-local}
APP_KEY=
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost:8088}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-heratio}
DB_USERNAME=${DB_USERNAME:-heratio}
DB_PASSWORD=${DB_PASSWORD:-heratio-test}

SESSION_DRIVER=database
CACHE_STORE=database

ELASTICSEARCH_HOST=${ELASTICSEARCH_HOST:-http://elasticsearch:9200}
ELASTICSEARCH_PREFIX=${ELASTICSEARCH_PREFIX:-heratio_}

HERATIO_STORAGE_PATH=${HERATIO_STORAGE_PATH:-/var/www/heratio/storage/uploads}
HERATIO_UPLOADS_PATH=${HERATIO_UPLOADS_PATH:-/var/www/heratio/storage/uploads}
HERATIO_BACKUPS_PATH=${HERATIO_BACKUPS_PATH:-/var/www/heratio/storage/backups}
EOF
    php artisan key:generate --force --no-interaction
fi

mkdir -p "${HERATIO_STORAGE_PATH:-storage/uploads}" \
         "${HERATIO_BACKUPS_PATH:-storage/backups}" \
         storage/framework/{sessions,views,cache,testing} \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ─── Stage 6-7: schema (only first boot) ─────────────────────────────────────
if [ ! -f "$MARKER" ]; then
    echo "[init] loading core schema"
    for sql in database/core/*.sql; do
        [ -f "$sql" ] || continue
        echo "[init]   $sql"
        mysql_run < "$sql" 2>&1 | head -5 || true
    done

    echo "[init] running heratio:install-bootstrap pass 1"
    php artisan heratio:install-bootstrap --pass=1 --no-interaction 2>&1 | tail -5 || true

    echo "[init] running heratio:install-bootstrap pass 2"
    php artisan heratio:install-bootstrap --pass=2 --no-interaction 2>&1 | tail -5 || true

    # ── Stage 8: seeds ───────────────────────────────────────────────────────
    echo "[init] loading seeds"
    for sql in database/seeds/*.sql; do
        [ -f "$sql" ] || continue
        echo "[init]   $sql"
        mysql_run < "$sql" 2>/dev/null | head -3 || echo "[init]   (warnings - continuing)"
    done

    # ── Stage 9: admin user ──────────────────────────────────────────────────
    echo "[init] creating admin user (${ADMIN_EMAIL:-admin@heratio.test})"
    HASH=$(php -r "echo password_hash(getenv('ADMIN_PASSWORD') ?: 'admin-please-change', PASSWORD_BCRYPT);")
    mysql_run <<SQL 2>/dev/null
INSERT INTO object (class_name, created_at, updated_at, serial_number)
VALUES ('QubitUser', NOW(), NOW(), 0);
SET @uid = LAST_INSERT_ID();
INSERT INTO actor (id, source_culture, description_status_id, description_detail_id, description_identifier)
VALUES (@uid, 'en', NULL, NULL, NULL);
INSERT INTO actor_i18n (id, culture, authorized_form_of_name)
VALUES (@uid, 'en', 'Administrator');
INSERT INTO \`user\` (id, username, email, password_hash, salt, active)
VALUES (@uid, 'admin', '${ADMIN_EMAIL:-admin@heratio.test}', '${HASH}', '', 1);
SQL

    touch "$MARKER"
    echo "[init] schema loaded - marker written to $MARKER"
else
    echo "[init] schema already loaded (marker present) - running pass-2 only"
    php artisan heratio:install-bootstrap --pass=2 --no-interaction 2>&1 | tail -3 || true
fi

# ─── Stage 11: Elasticsearch indices ─────────────────────────────────────────
if [ -n "${ELASTICSEARCH_HOST:-}" ]; then
    if curl -fsS "$ELASTICSEARCH_HOST/heratio_qubitinformationobject" -o /dev/null 2>/dev/null; then
        echo "[init] ES indices present"
    else
        echo "[init] creating ES indices"
        php artisan ahg:es-reindex --drop --no-interaction 2>&1 | tail -3 || \
            echo "[init] ES create failed - try manually: php artisan ahg:es-reindex --drop"
    fi
fi

# ─── Laravel cache warming ───────────────────────────────────────────────────
php artisan config:cache  --no-interaction 2>/dev/null || true
php artisan route:cache   --no-interaction 2>/dev/null || true
php artisan view:cache    --no-interaction 2>/dev/null || true

echo "[init] === Heratio bootstrap complete ==="
