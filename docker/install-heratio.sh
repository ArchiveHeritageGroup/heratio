#!/bin/bash
# =============================================================================
# install-heratio.sh - one-shot installer for client deployments.
#
# Pulls the prebuilt image from ghcr.io/archiveheritagegroup/heratio,
# downloads the client compose file + env template, prompts for an admin
# password and DB password, and brings the stack up.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/ArchiveHeritageGroup/heratio/main/docker/install-heratio.sh | bash
#
# Or with a specific version:
#   HERATIO_VERSION=v1.41.3 \
#       curl -fsSL https://.../install-heratio.sh | bash
#
# What this does NOT do:
#   - Run any AI service. Heratio is an AI client. After install, configure
#     remote AI endpoints in the Settings dashboard. The image is intentionally
#     free of model weights and runtimes.
#   - Set up TLS. Front the container's port (default 8088) with your own
#     reverse proxy (nginx, Caddy, Traefik) for HTTPS.
#
# @copyright  Johan Pieterse / Plain Sailing
# @license    AGPL-3.0-or-later
# =============================================================================

set -euo pipefail

REPO="ArchiveHeritageGroup/heratio"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/main/docker"
INSTALL_DIR="${INSTALL_DIR:-./heratio}"
VERSION="${HERATIO_VERSION:-latest}"

echo
echo "─── Heratio one-shot installer ─────────────────────────────────────────"
echo "  Image:    ghcr.io/archiveheritagegroup/heratio:${VERSION}"
echo "  Install:  ${INSTALL_DIR}"
echo "  Policy:   AI is REMOTE-ONLY. The image contains no model weights."
echo "─────────────────────────────────────────────────────────────────────────"
echo

# ── Pre-flight ───────────────────────────────────────────────────────────────
command -v docker >/dev/null 2>&1 || {
    echo "ERROR: docker is not installed. See https://docs.docker.com/engine/install/"
    exit 1
}
docker compose version >/dev/null 2>&1 || {
    echo "ERROR: 'docker compose' (v2 plugin) is not available."
    exit 1
}

# ── Install dir ──────────────────────────────────────────────────────────────
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# ── Fetch compose + env template ─────────────────────────────────────────────
echo "[1/5] downloading docker-compose.client.yml + env template ..."
curl -fL -o docker-compose.yml    "${RAW_BASE}/docker-compose.client.yml"
curl -fL -o .env.docker.example   "${RAW_BASE}/.env.docker.example"

# ── First-run: collect credentials ───────────────────────────────────────────
if [ ! -f .env.docker ]; then
    cp .env.docker.example .env.docker

    if [ -t 0 ]; then
        echo
        echo "[2/5] first-run setup - please choose a few values:"
        read -rp "  Admin email          [admin@heratio.local]: " ADMIN_EMAIL_IN
        read -rsp "  Admin password       (required)            : " ADMIN_PASSWORD_IN; echo
        read -rsp "  Database password    (required)            : " DB_PASSWORD_IN; echo
        read -rsp "  DB root password     (required)            : " DB_ROOT_PASSWORD_IN; echo
        read -rp "  Host port            [8088]               : " PORT_IN

        ADMIN_EMAIL_IN="${ADMIN_EMAIL_IN:-admin@heratio.local}"
        PORT_IN="${PORT_IN:-8088}"

        sed -i.bak \
            -e "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=${ADMIN_EMAIL_IN}|" \
            -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=${ADMIN_PASSWORD_IN}|" \
            -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD_IN}|" \
            -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD_IN}|" \
            -e "s|^HERATIO_PORT=.*|HERATIO_PORT=${PORT_IN}|" \
            .env.docker
        rm -f .env.docker.bak
        chmod 600 .env.docker
    else
        echo "  (non-interactive run - edit ${INSTALL_DIR}/.env.docker before 'docker compose up')"
    fi
else
    echo "[2/5] .env.docker already exists - reusing"
fi

# ── ES kernel knob ───────────────────────────────────────────────────────────
echo
echo "[3/5] checking host kernel (ES needs vm.max_map_count >= 262144)"
CUR=$(sysctl -n vm.max_map_count 2>/dev/null || echo 0)
if [ "$CUR" -lt 262144 ]; then
    if [ "$EUID" -eq 0 ]; then
        sysctl -w vm.max_map_count=262144 >/dev/null
        grep -q "^vm.max_map_count" /etc/sysctl.conf 2>/dev/null \
            || echo "vm.max_map_count=262144" >> /etc/sysctl.conf
        echo "  set vm.max_map_count=262144 (persisted to /etc/sysctl.conf)"
    else
        echo "  WARNING: vm.max_map_count=${CUR} is too low. Run as root:"
        echo "  sudo sysctl -w vm.max_map_count=262144 && echo 'vm.max_map_count=262144' | sudo tee -a /etc/sysctl.conf"
    fi
else
    echo "  OK (${CUR})"
fi

# ── Pull + up ────────────────────────────────────────────────────────────────
echo
echo "[4/5] pulling image (Heratio + MySQL + Elasticsearch)"
docker compose --env-file .env.docker pull

echo
echo "[5/5] starting stack"
docker compose --env-file .env.docker up -d

echo
echo "─── Heratio is starting ─────────────────────────────────────────────────"
echo "  First boot loads ~995-table schema + seeds (~3 min)."
echo "  Watch:  cd ${INSTALL_DIR} && docker compose logs -f heratio"
echo "  Then:   open http://localhost:$(grep '^HERATIO_PORT=' .env.docker | cut -d= -f2)/"
echo "  Login:  the email + password you chose above"
echo
echo "  AI features (HTR, NER, condition scan, semantic search):"
echo "    Configure remote endpoints in Settings → AI after first login."
echo "    Heratio talks HTTP - bring your own Ollama / vLLM host."
echo "─────────────────────────────────────────────────────────────────────────"
