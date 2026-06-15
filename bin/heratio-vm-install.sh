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
# LAN bridge + a STATIC IP: this environment's LAN has no DHCP for new guests
# (Mogalakwena uses a static address), so cloud-init assigns a fixed IP via a
# netplan network-config in the seed. Override the address with --ip; use
# --bridge <name> to change the bridge.
NET_SPEC="bridge=br0"
STATIC_IP="192.168.0.60"            # free on the LAN (ping-clear + absent from ARP); override with --ip
GATEWAY="192.168.0.1"
DNS1="192.168.0.1"
DNS2="8.8.8.8"
REPO_URL="https://github.com/ArchiveHeritageGroup/heratio.git"   # PUBLIC - no creds
BRANCH="main"
APP_DOMAIN=""                       # default: the static IP
ADMIN_EMAIL="admin@heratio.local"
ADMIN_PASSWORD=""                   # default: generated, printed at the end
FORCE=0

# VMs this script must never create-over / destroy (live production clients).
PROTECTED_VMS=("Mogalakwena")

while [[ $# -gt 0 ]]; do
    case "$1" in
        --name)            VM_NAME="$2"; shift 2 ;;
        --ip)              STATIC_IP="$2"; shift 2 ;;
        --bridge)          NET_SPEC="bridge=$2"; shift 2 ;;
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
# Console password for ahgadmin (SSH stays key-only; this is for `virsh console` debugging).
VM_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 16)"

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
  - qemu-guest-agent
chpasswd:
  expire: false
  list: |
    ahgadmin:$VM_PASSWORD
runcmd:
  # qemu-guest-agent lets the host read the bridged guest's IP (virsh --source agent).
  - systemctl enable --now qemu-guest-agent || true
  # Elasticsearch needs a higher map count (for when you run the install).
  - sysctl -w vm.max_map_count=262144
  - echo "vm.max_map_count=262144" >> /etc/sysctl.conf
  - touch /var/log/heratio-vm-ready
final_message: "VM base ready - SSH/console in and run the install yourself"
EOF

cat > "$SEED_DIR/meta-data" <<EOF
instance-id: $VM_NAME
local-hostname: $VM_NAME
EOF

# Static network (the LAN has no DHCP). NoCloud reads this 'network-config' (netplan v2).
cat > "$SEED_DIR/network-config" <<EOF
version: 2
ethernets:
  primary:
    match:
      name: "en*"
    dhcp4: false
    addresses:
      - $STATIC_IP/24
    routes:
      - to: default
        via: $GATEWAY
    nameservers:
      addresses: [$DNS1, $DNS2]
EOF

# Build the cloud-init seed ISO with whatever tool is present (this host has
# xorriso, not genisoimage). network-config is included so the static IP applies.
# Errors are NOT swallowed so a failure is visible.
if command -v genisoimage >/dev/null 2>&1; then
    genisoimage -output "$SEED_ISO" -volid cidata -joliet -rock "$SEED_DIR/user-data" "$SEED_DIR/meta-data" "$SEED_DIR/network-config"
elif command -v xorriso >/dev/null 2>&1; then
    xorriso -as genisoimage -output "$SEED_ISO" -volid cidata -joliet -rock "$SEED_DIR/user-data" "$SEED_DIR/meta-data" "$SEED_DIR/network-config"
elif command -v cloud-localds >/dev/null 2>&1; then
    cloud-localds --network-config="$SEED_DIR/network-config" "$SEED_ISO" "$SEED_DIR/user-data" "$SEED_DIR/meta-data"
else
    echo "need genisoimage, xorriso, or cloud-localds to build the cloud-init seed ISO" >&2; exit 1
fi

# ── 3. disk + virt-install ───────────────────────────────────────────────────
echo "==> creating $VM_DISK_GB GB disk for $VM_NAME"
cp --reflink=auto "$CLOUD_IMG" "$DISK"
# Use libvirt's qemu-img explicitly - a bare 'qemu-img' on this host resolves to
# the android-sdk build first on PATH, which is not the qcow2 tool we want.
QEMU_IMG=/usr/bin/qemu-img; [[ -x "$QEMU_IMG" ]] || QEMU_IMG=$(command -v qemu-img)
"$QEMU_IMG" resize "$DISK" "${VM_DISK_GB}G"

echo "==> virt-install $VM_NAME ($VM_CPUS vCPU, $VM_RAM_MB MB, $VM_DISK_GB GB) on $NET_SPEC, static IP $STATIC_IP"
virt-install \
    --name      "$VM_NAME" \
    --memory    "$VM_RAM_MB" \
    --vcpus     "$VM_CPUS" \
    --disk      "path=$DISK,format=qcow2,bus=virtio" \
    --disk      "path=$SEED_ISO,device=cdrom" \
    --os-variant ubuntu24.04 \
    --network   "$NET_SPEC,model=virtio" \
    --graphics  none \
    --console   pty,target_type=serial \
    --import \
    --noautoconsole

# ── 4. wait for the guest to come up on its STATIC IP (deterministic) ────────
IP="$STATIC_IP"
echo "==> waiting for SSH on the static IP $IP (first boot + cloud-init, ~3-5 min)"
UP=0
for i in {1..96}; do
    if ssh -o BatchMode=yes -o ConnectTimeout=4 -o StrictHostKeyChecking=accept-new \
           ahgadmin@"$IP" 'test -f /var/log/heratio-vm-ready' 2>/dev/null; then UP=1; break; fi
    sleep 5
done
if [[ "$UP" -ne 1 ]]; then
    echo "VM not reachable at $IP after ~8 min." >&2
    echo "Debug from the console: sudo virsh console $VM_NAME  (login: ahgadmin / $VM_PASSWORD ; Ctrl-] to detach)" >&2
    echo "Inside, check: ip -4 addr ; cloud-init status --long ; cat /etc/netplan/50-cloud-init.yaml" >&2
    exit 1
fi

# ── 5. STOP here - the VM is up and reachable; you run the pull + install ────
DOMAIN="${APP_DOMAIN:-$IP}"
echo
echo "==================================================================="
echo "Fresh Heratio VM is UP and reachable - install is yours to run."
echo "  Host:    .112 (untouched)"
echo "  VM name: $VM_NAME"
echo "  IP:      $IP"
echo "  SSH:     ssh ahgadmin@$IP                  (your key)"
echo "  Console: sudo virsh console $VM_NAME       (login ahgadmin / $VM_PASSWORD ; Ctrl-] detaches)"
echo
echo "  Pull + full install - run these INSIDE the VM:"
echo "    ssh ahgadmin@$IP"
echo "    sudo git clone --branch $BRANCH $REPO_URL /usr/share/nginx/heratio"
echo "    cd /usr/share/nginx/heratio"
echo "    sudo bash bin/install-host-tools.sh"
echo "    sudo bash bin/install --domain=$DOMAIN --admin-email=$ADMIN_EMAIL"
echo "==================================================================="
