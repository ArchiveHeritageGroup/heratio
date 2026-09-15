#!/bin/bash
# Exercises docker/init.sh without Docker: stubs mysql/php/curl and runs the real
# script against a temp app dir, checking that the core schema is never loaded over
# an existing install and that .env (APP_KEY) survives a container recreate.
#
#   bash docker/test-init.sh
set -uo pipefail
HERE=$(cd "$(dirname "$0")" && pwd)
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
fail=0

mkdir -p "$T/bin"
cat > "$T/bin/mysql" <<'SH'
#!/bin/bash
# DB check query -> $STUB_DB_TABLES (or fail when STUB_DB_FAIL=1); anything else is a load
if [[ "$*" == *information_schema.tables* ]]; then
    [ "${STUB_DB_FAIL:-0}" = 1 ] && { echo "ERROR 2002 cannot connect" >&2; exit 1; }
    echo "${STUB_DB_TABLES:-0}"; exit 0
fi
cat > /dev/null; echo "mysql-load" >> "$STUB_LOG"
SH
cat > "$T/bin/php" <<'SH'
#!/bin/bash
echo "php $*" >> "$STUB_LOG"
if [[ "$*" == *key:generate* ]]; then sed -i "s/^APP_KEY=$/APP_KEY=base64:NEW$RANDOM/" .env; fi
if [[ "$*" == -r* ]]; then echo hash; fi
SH
printf '#!/bin/bash\nexit 1\n' > "$T/bin/curl"
printf '#!/bin/bash\nexit 0\n' > "$T/bin/chown"
chmod +x "$T/bin/"*

run() { # name, then env assignments
    local name=$1; shift
    rm -f "$T/log"; mkdir -p "$T/app/database/core" "$T/app/database/seeds"
    echo "DROP TABLE IF EXISTS x;" > "$T/app/database/core/00_core_schema.sql"
    sed "s#^cd /var/www/heratio#cd $T/app#" "$HERE/init.sh" > "$T/init.sh"
    env PATH="$T/bin:$PATH" STUB_LOG="$T/log" HERATIO_STATE_DIR="$T/state" \
        DB_PASSWORD=x DB_ROOT_PASSWORD=x ADMIN_PASSWORD=x ELASTICSEARCH_HOST= "$@" \
        bash "$T/init.sh" > "$T/out" 2>&1
    echo $?
}
check() { # description, condition
    if eval "$2"; then echo "ok   - $1"; else echo "FAIL - $1"; fail=1; sed 's/^/     | /' "$T/out"; fi
}
loads() { cat "$T/log" 2>/dev/null | grep -c mysql-load; }

# 1. Fresh install: empty DB, no state
rm -rf "$T/app" "$T/state"
rc=$(run fresh)
check "fresh: exits 0" '[ "$rc" = 0 ]'
check "fresh: loads the core schema" '[ "$(loads)" -ge 1 ]'
check "fresh: writes the marker into the state dir" '[ -f "$T/state/.heratio-installed" ]'
check "fresh: persists .env with a generated APP_KEY" 'grep -q "^APP_KEY=base64:NEW" "$T/state/.env"'
KEY=$(grep ^APP_KEY= "$T/state/.env")

# 2. Recreate: new container fs (no .env), same state volume, populated DB
rm -rf "$T/app"
rc=$(run recreate STUB_DB_TABLES=1)
check "recreate: exits 0" '[ "$rc" = 0 ]'
check "recreate: does not load the schema" '[ "$(loads)" = 0 ]'
check "recreate: restores the same APP_KEY" '[ "$(grep ^APP_KEY= "$T/app/.env")" = "$KEY" ]'
check "recreate: does not generate a new key" '! grep -q key:generate "$T/log"'

# 3. Marker lost (e.g. pre-volume container recreated) but the DB already holds Heratio
rm -rf "$T/app" "$T/state/.heratio-installed"
rc=$(run lost-marker STUB_DB_TABLES=1)
check "lost marker + existing DB: exits 0" '[ "$rc" = 0 ]'
check "lost marker + existing DB: does NOT load the schema" '[ "$(loads)" = 0 ]'
check "lost marker + existing DB: writes the marker" '[ -f "$T/state/.heratio-installed" ]'

# 4. Marker lost and the DB cannot be checked: fail closed
rm -rf "$T/app" "$T/state/.heratio-installed"
rc=$(run db-down STUB_DB_FAIL=1)
check "db unreachable: exits non-zero" '[ "$rc" != 0 ]'
check "db unreachable: does NOT load the schema" '[ "$(loads)" = 0 ]'

exit $fail
