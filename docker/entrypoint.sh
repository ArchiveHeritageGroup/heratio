#!/bin/bash
# Container entrypoint - runs heratio-init once (idempotent), then execs the
# normal CMD (supervisord). Splitting init from supervisord means failures in
# the install pipeline crash the container immediately with a clear log,
# rather than half-starting and serving 500s.

set -euo pipefail

# Wait for MySQL - depends_on with healthcheck handles this in compose, but
# we keep a belt-and-braces loop here for `docker run` use without compose.
echo "[entrypoint] waiting for ${DB_HOST:-mysql}:${DB_PORT:-3306} ..."
for i in {1..60}; do
    if mysqladmin ping -h "${DB_HOST:-mysql}" -u "${DB_USERNAME:-heratio}" \
            -p"${DB_PASSWORD:-heratio-test}" --silent 2>/dev/null; then
        echo "[entrypoint] MySQL up after ${i}s"
        break
    fi
    sleep 1
done

# Wait for ES (skip if unset)
if [ -n "${ELASTICSEARCH_HOST:-}" ]; then
    echo "[entrypoint] waiting for $ELASTICSEARCH_HOST ..."
    for i in {1..60}; do
        if curl -fsS "$ELASTICSEARCH_HOST/_cluster/health" >/dev/null 2>&1; then
            echo "[entrypoint] ES up after ${i}s"
            break
        fi
        sleep 1
    done
fi

# First-boot bootstrap - idempotent, safe to run on every container start.
heratio-init

exec "$@"
