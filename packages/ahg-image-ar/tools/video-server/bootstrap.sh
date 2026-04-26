#!/usr/bin/env bash
# Heratio image-to-video server — one-shot bootstrap.
# Designed to run on the AI host (192.168.0.78) as a sudoer.
#
# Usage (from the Heratio server):
#   scp -r packages/ahg-image-ar/tools/video-server/ ahg@192.168.0.78:/tmp/
#   ssh ahg@192.168.0.78 'sudo bash /tmp/video-server/bootstrap.sh'
#
# Idempotent — safe to re-run. Resumes a partial install.

set -euo pipefail

INSTALL_DIR="/opt/heratio-video-server"
DATA_DIR="/var/lib/video-server"
SERVICE_USER="${SERVICE_USER:-ahg}"
SERVICE_GROUP="${SERVICE_GROUP:-ahg}"
PYTHON="${PYTHON:-python3}"

log() { printf '\n\033[1;36m== %s ==\033[0m\n' "$*"; }
ok()  { printf '\033[1;32m✓\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m!\033[0m %s\n' "$*"; }

[[ $EUID -eq 0 ]] || { warn "must be run with sudo"; exit 1; }

log "0. Preflight"
if ! command -v nvidia-smi >/dev/null; then
  warn "nvidia-smi not found — diffusers will fall back to CPU and be unusably slow."
fi
nvidia-smi --query-gpu=name,memory.total,memory.free --format=csv 2>/dev/null || true

if ! command -v "$PYTHON" >/dev/null; then
  warn "$PYTHON not installed; trying to install python3-venv + python3-pip"
  apt-get update -qq
  apt-get install -y -qq python3 python3-venv python3-pip
fi
PY_VERSION=$($PYTHON -c 'import sys; print("%d.%d" % sys.version_info[:2])')
echo "Python: $PY_VERSION"
case "$PY_VERSION" in
  3.10|3.11|3.12) ok "supported";;
  *) warn "Python $PY_VERSION is outside tested range 3.10-3.12 — torch wheels may not exist";;
esac

if ! command -v ffmpeg >/dev/null; then
  log "Installing ffmpeg"
  apt-get install -y -qq ffmpeg
fi

log "1. Layout"
install -d -o "$SERVICE_USER" -g "$SERVICE_GROUP" "$INSTALL_DIR" "$DATA_DIR/models" "$DATA_DIR/hf"
ok "$INSTALL_DIR + $DATA_DIR ready (owned by $SERVICE_USER)"

log "2. Copy server files"
SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
for f in server.py requirements.txt heratio-video-server.service; do
  install -m 0644 -o "$SERVICE_USER" -g "$SERVICE_GROUP" "$SRC_DIR/$f" "$INSTALL_DIR/$f"
done
ok "files installed"

log "3. Python venv + dependencies (this is the slow step — 3-8 min)"
sudo -u "$SERVICE_USER" bash -c "
  cd '$INSTALL_DIR'
  if [[ ! -d .venv ]]; then
    $PYTHON -m venv .venv
  fi
  . .venv/bin/activate
  pip install --upgrade pip wheel --quiet
  pip install -r requirements.txt --quiet
"
ok "dependencies installed"

log "4. Pre-warm Stable Video Diffusion weights (~5 GB download, first-time only)"
warn "If this is the first install, expect ~5-15 min depending on your network."
sudo -u "$SERVICE_USER" bash -c "
  cd '$INSTALL_DIR'
  . .venv/bin/activate
  HF_HOME='$DATA_DIR/hf' python -c \"
from diffusers import StableVideoDiffusionPipeline
StableVideoDiffusionPipeline.from_pretrained(
    'stabilityai/stable-video-diffusion-img2vid',
    cache_dir='$DATA_DIR/models'
)
print('SVD weights ready')
\"
"
ok "weights cached"

log "5. systemd unit"
install -m 0644 "$SRC_DIR/heratio-video-server.service" /etc/systemd/system/heratio-video-server.service
# Patch the unit if SERVICE_USER overridden.
if [[ "$SERVICE_USER" != "ahg" ]]; then
  sed -i "s/^User=ahg/User=$SERVICE_USER/; s/^Group=ahg/Group=$SERVICE_GROUP/" /etc/systemd/system/heratio-video-server.service
fi
systemctl daemon-reload
systemctl enable --now heratio-video-server
sleep 3
systemctl is-active --quiet heratio-video-server && ok "service started" || warn "service failed — see: journalctl -u heratio-video-server -n 50"

log "6. Firewall (UFW)"
if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
  ufw allow from 192.168.0.0/24 to any port 5052 proto tcp || true
  ok "ufw rule added"
else
  warn "ufw not active — make sure 5052 is reachable from the Heratio host"
fi

log "7. Smoke test"
sleep 2
if curl -fsS --max-time 5 http://localhost:5052/health >/dev/null; then
  ok "health endpoint responding"
  curl -s http://localhost:5052/health | python3 -m json.tool
else
  warn "health endpoint not responding yet — service may still be loading the model"
  warn "check progress: sudo journalctl -u heratio-video-server -f"
fi

cat <<EOF

=========================================================================
INSTALL COMPLETE.

Service: heratio-video-server.service (listens on :5052)
Logs:    sudo journalctl -u heratio-video-server -f
Restart: sudo systemctl restart heratio-video-server

From the Heratio host, verify reachability:
  curl http://192.168.0.78:5052/health

Then on Heratio:
  php artisan ahg:image-ar --health
  php artisan ahg:image-ar --object-id=<id> --force

NOTE: First /animate request still takes a few extra seconds because the
SVD pipeline initialises lazily on first use. After that, expect 3-8 min
per generation on the 8 GB GPU.
=========================================================================
EOF
