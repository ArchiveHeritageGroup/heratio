#!/bin/bash
# =============================================================================
# heratio-vm-install.sh - provision a libvirt VM and install Heratio inside it
# from a PUBLIC GitHub clone via bin/install (the standalone full installer).
# NOT Docker. The sibling bin/heratio-vm.sh does the Docker test stack instead.
#
# SAFETY (this host, .112, is the LIVE prod/dev server and also runs the
# production client VM 'Mogalakwena'):
#   * NOTHING is installed on the host. The host only: caches the cloud image,
#     writes a per-VM cloud-init seed, virt-installs the guest, and SSHes INTO
#     the guest to run the install. git clone / install-host-tools / bin/install
#     all execute on the GUEST only.
#   * Refuses to act on any pre-existing VM it did not create this run, and
#     hard-refuses the protected production VM name(s).
#
# Run on the libvirt host (.112):
#   sudo bin/heratio-vm-install.sh
#   sudo bin/heratio-vm-install.sh --name heratio-dev --admin-email you@x.co.za
#   sudo bin/heratio-vm-install.sh --force          # destroy+recreate THIS vm only
#
# @copyright  Johan Pieterse / Plain Sailing
# @license    AGPL-3.0-or-later
# =============================================================================
set -euo pipefail

VM_NAME="heratio-dev"
VM_RAM_MB=4096
VM_CPUS=4
VM_DISK_GB=40
NET_BRIDGE="br0"
REPO_URL="https://github.com/ArchiveHeritageGroup/heratio.git"   # PUBLIC - no creds
BRANCH="main"
APP_DOMAIN=""                       # default: the guest's DHCP IP
ADMIN_EMAIL="admin@heratio.local"
ADMIN_PASSWORD=""                   # default: generated, printed at the end
FORCE=0

# VMs this script must never create-over / destroy (live production clients).
PROTECTED_VMS=("Mogalakwena")

while [[ $# -gt 0 ]]; do
    case "$1" in
        --name)            VM_NAME="$2"; shift 2 ;;
        --cpus)            VM_CPUS="$2"; shift 2 ;;
        --ram-mb)          VM_RAM_MB="$2"; shift 2 ;;
        --disk-gb)         VM_DISK_GB="$2"; shift 2 ;;
        --branch)          BRANCH="$2"; shift 2 ;;
        --repo)            REPO_URL="$2"; shift 2 ;;
        --domain)          APP_DOMAIN="$2"; shift 2 ;;
        --admin-email)     ADMIN_EMAIL="$2"; shift 2 ;;
        --admin-password)  ADMIN_PASSWORD="$2"; shift 2 ;;
        --force)           FORCE=1; shift ;;
        *) echo "unknown arg: $1"; exit 2 ;;
    esac
done

# ── safety: never touch a protected production VM ────────────────────────────
for p in "${PROTECTED_VMS[@]}"; do
    if [[ "$VM_NAME" == "$p" ]]; then
        echo "REFUSING: '$VM_NAME' is a protected production VM." >&2; exit 1
    fi
done

# If the target VM already exists, only proceed with --force (and only ever act
# on THIS named VM - we never enumerate/destroy others).
if virsh dominfo "$VM_NAME" >/dev/null 2>&1; then
    if [[ "$FORCE" -ne 1 ]]; then
        echo "VM '$VM_NAME' already exists. Re-run with --force to destroy+recreate it." >&2; exit 1
    fi
    echo "==> --force: destroying existing '$VM_NAME' (this VM only)"
    virsh destroy "$VM_NAME" >/dev/null 2>&1 || true
    virsh undefine "$VM_NAME" --remove-all-storage >/dev/null 2>&1 || true
fi

[[ -n "$ADMIN_PASSWORD" ]] || ADMIN_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 16)"

IMG_DIR=/var/lib/libvirt/images
CACHE_DIR=/var/lib/libvirt/cache
SEED_DIR=$IMG_DIR/$VM_NAME-seed
DISK=$IMG_DIR/$VM_NAME.qcow2
SEED_ISO=$SEED_DIR/seed.iso
CLOUD_IMG_URL=https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img
CLOUD_IMG=$CACHE_DIR/noble-server-cloudimg-amd64.img
mkdir -p "$CACHE_DIR" "$SEED_DIR"

# ── 1. cache the cloud image (qcow2, NOT the live-server ISO) ────────────────
if [[ ! -f "$CLOUD_IMG" ]]; then
    echo "==> downloading Ubuntu 24.04 cloud image (one-time)"
    curl -fL -o "$CLOUD_IMG.tmp" "$CLOUD_IMG_URL"
    mv "$CLOUD_IMG.tmp" "$CLOUD_IMG"
fi

# ── 2. cloud-init seed: base packages only (NO install here - install runs   ─
#       post-boot over SSH so failures are visible). No secrets in the seed.  ─
SSH_PUB=$(cat /root/.ssh/id_ed25519.pub 2>/dev/null || cat /root/.ssh/id_rsa.pub 2>/dev/null)
[[ -n "$SSH_PUB" ]] || { echo "no ssh pubkey at /root/.ssh/" >&2; exit 1; }

cat > "$SEED_DIR/user-data" <<EOF
#cloud-config
hostname: $VM_NAME
manage_etc_hosts: true
ssh_pwauth: false
disable_root: false
users:
  - name: ahgadmin
    sudo: ALL=(ALL) NOPASSWD:ALL
    shell: /bin/bash
    ssh_authorized_keys:
      - $SSH_PUB
package_update: true
package_upgrade: false
packages:
  - ca-certificates
  - curl
  - git
runcmd:
  # Elasticsearch needs a higher map count; set it before the install runs.
  - sysctl -w vm.max_map_count=262144
  - echo "vm.max_map_count=262144" >> /etc/sysctl.conf
  - touch /var/log/heratio-vm-ready
final_message: "base ready; host will SSH in to install Heratio"
EOF

cat > "$SEED_DIR/meta-data" <<EOF
instance-id: $VM_NAME
local-hostname: $VM_NAME
EOF

genisoimage -output "$SEED_ISO" -volid cidata -joliet -rock \
            "$SEED_DIR/user-data" "$SEED_DIR/meta-data" >/dev/null 2>&1

# ── 3. disk + virt-install ───────────────────────────────────────────────────
echo "==> creating $VM_DISK_GB GB disk for $VM_NAME"
cp --reflink=auto "$CLOUD_IMG" "$DISK"
qemu-img resize "$DISK" "${VM_DISK_GB}G"

echo "==> virt-install $VM_NAME ($VM_CPUS vCPU, $VM_RAM_MB MB, $VM_DISK_GB GB) on $NET_BRIDGE"
virt-install \
    --name      "$VM_NAME" \
    --memory    "$VM_RAM_MB" \
    --vcpus     "$VM_CPUS" \
    --disk      "path=$DISK,format=qcow2,bus=virtio" \
    --disk      "path=$SEED_ISO,device=cdrom" \
    --os-variant ubuntu24.04 \
    --network   "bridge=$NET_BRIDGE,model=virtio" \
    --graphics  none \
    --console   pty,target_type=serial \
    --import \
    --noautoconsole

# ── 4. wait for the guest IP + cloud-init ────────────────────────────────────
echo "==> waiting for DHCP lease"
IP=""
for i in {1..60}; do
    IP=$(virsh domifaddr "$VM_NAME" 2>/dev/null | awk '/ipv4/{split($4,a,"/"); print a[1]; exit}')
    [[ -n "$IP" ]] && break
    sleep 2
done
[[ -n "$IP" ]] || { echo "no IP after 2 min - check 'virsh console $VM_NAME'" >&2; exit 1; }
echo "==> guest IP: $IP"

echo "==> waiting for cloud-init base"
for i in {1..90}; do
    if ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new \
           ahgadmin@"$IP" 'test -f /var/log/heratio-vm-ready' 2>/dev/null; then break; fi
    sleep 5
done

# ── 5. install Heratio INSIDE the guest (clone + host-tools + bin/install) ───
#       Every command below runs on the GUEST via SSH - never on this host.
DOMAIN="${APP_DOMAIN:-$IP}"
echo "==> [guest $IP] cloning $REPO_URL ($BRANCH) and running the full install"
ssh -o StrictHostKeyChecking=accept-new ahgadmin@"$IP" "bash -se" <<REMOTE
set -euo pipefail
sudo mkdir -p /usr/share/nginx
if [ ! -d /usr/share/nginx/heratio/.git ]; then
  sudo git clone --branch "$BRANCH" "$REPO_URL" /usr/share/nginx/heratio
fi
cd /usr/share/nginx/heratio
sudo bash bin/install-host-tools.sh
sudo bash bin/install --non-interactive --domain="$DOMAIN" --admin-email="$ADMIN_EMAIL" --admin-password='$ADMIN_PASSWORD'
REMOTE

echo
echo "==================================================================="
echo "Heratio installed in a fresh VM (GitHub pull + full bin/install)"
echo "  Host:    .112 (untouched - install ran inside the guest only)"
echo "  VM name: $VM_NAME"
echo "  IP:      $IP"
echo "  Web:     http://$DOMAIN/"
echo "  SSH:     ssh ahgadmin@$IP"
echo "  Admin:   $ADMIN_EMAIL  /  $ADMIN_PASSWORD"
echo "  Console: virsh console $VM_NAME   (Ctrl-] to detach)"
echo "==================================================================="
