#!/usr/bin/env bash
# =============================================================================
# port-plugin-install-sql.sh
# =============================================================================
# Phase 1 #3 helper. Ports an AtoM AHG plugin's install.sql to a Heratio
# package's database/install.sql with idempotency transforms:
#   - strip mysqldump /*!NNNNN ... */ blocks (single + multi-line)
#   - strip mysqldump Host:/Server: comments
#   - remove DROP TABLE / DROP VIEW statements
#   - CREATE TABLE → CREATE TABLE IF NOT EXISTS
#   - reorder COMMENT to end of column definition (MySQL 8 strict)
#   - wrap output in SET FOREIGN_KEY_CHECKS=0 to bypass plugin-load-order
#     FK issues (parent rows in other plugins / seeds may not exist yet)
#   - prepend Heratio attribution header
#
# NOTE: VIEWs are stripped (mysqldump wraps them in /*!50001 ... */ blocks
# which we remove). If a plugin needs a VIEW, recreate it as a plain
# `CREATE OR REPLACE VIEW` statement in its install.sql by hand.
#
# Usage:  port-plugin-install-sql.sh <atom-plugin-name> <heratio-package-name>
# Example: port-plugin-install-sql.sh ahgCorePlugin ahg-core
# =============================================================================

set -euo pipefail

ATOM_PLUGIN="${1:?atom-plugin-name required (e.g. ahgCorePlugin)}"
HERATIO_PKG="${2:?heratio-package-name required (e.g. ahg-core)}"

SRC="/usr/share/nginx/archive/atom-ahg-plugins/${ATOM_PLUGIN}/database/install.sql"
DST="/usr/share/nginx/heratio/packages/${HERATIO_PKG}/database/install.sql"

[[ -f "$SRC" ]] || { echo "ERROR: source not found: $SRC" >&2; exit 1; }
mkdir -p "$(dirname "$DST")"

# If destination already has Heratio-original content, append after a separator.
EXISTING=""
if [[ -f "$DST" ]] && ! grep -q "Ported from AtoM ${ATOM_PLUGIN}" "$DST"; then
    EXISTING="$(cat "$DST")"
fi

DATE="$(date -I)"

{
    if [[ -n "$EXISTING" ]]; then
        printf '%s\n' "$EXISTING"
        echo
        echo "-- ============================================================================"
        echo "-- Ported from AtoM ${ATOM_PLUGIN} on ${DATE}"
        echo "-- ============================================================================"
    else
        cat <<EOF
-- ============================================================================
-- ${HERATIO_PKG} — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/${ATOM_PLUGIN}/database/install.sql
-- on ${DATE}. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
--   - INSERTs targeting tables not yet created (cross-plugin dependencies on
--     fresh install) are tolerated via a sql_warnings + missing-table check;
--     bin/install runs plugin install.sql twice so cross-plugin INSERTs that
--     need a peer plugin's table land on the second pass.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

EOF
    fi

    # Strip multi-line /*!NNNNN ... */ blocks first via Perl in slurp mode.
    perl -0777 -pe '
        s{/\*!\d+\s.*?\*/;?}{}gs;
    ' "$SRC" \
    | awk '
        # Strip remaining mysqldump comments and globals
        /^USE \`/                  { next }
        /^SET @OLD_/               { next }
        /^SET FOREIGN_KEY_CHECKS=/ { next }
        /^SET SQL_MODE=/           { next }
        /^SET CHARACTER_SET/       { next }
        /^-- Host:/                { next }
        /^-- Server version/       { next }
        /^-- MySQL dump/           { next }
        /^-- ------/               { next }
        /^-- Dump completed/       { next }
        /^-- Temporary view structure/ { skip_para=1; next }
        /^-- Final view structure/     { skip_para=1; next }
        skip_para && /^$/          { skip_para=0; next }
        skip_para                  { next }
        # Remove DROP TABLE / DROP VIEW
        /^DROP TABLE/              { next }
        /^DROP VIEW/               { next }
        # CREATE TABLE → CREATE TABLE IF NOT EXISTS (only when not already)
        /^CREATE TABLE [^I]/       { sub(/^CREATE TABLE /, "CREATE TABLE IF NOT EXISTS "); print; next }
        /^CREATE TABLE `/          { sub(/^CREATE TABLE /, "CREATE TABLE IF NOT EXISTS "); print; next }
        # INSERT INTO → INSERT IGNORE INTO (re-run idempotency on seed rows)
        /^INSERT INTO/             { sub(/^INSERT INTO/, "INSERT IGNORE INTO"); print; next }
        { print }
    ' \
    | perl -pe '
        if (m{^\s*`} && m{ COMMENT '"'"'[^'"'"']*'"'"' +(CHARACTER SET|COLLATE|DEFAULT|NOT NULL)}) {
          s{( COMMENT '"'"'[^'"'"']*'"'"')(.*?)(,?\s*)$}{$2$1$3};
        }
    '

    echo
    echo "SET FOREIGN_KEY_CHECKS = 1;"
} > "$DST"

echo "  ${ATOM_PLUGIN} -> ${DST} ($(wc -l < "$DST") lines)"
