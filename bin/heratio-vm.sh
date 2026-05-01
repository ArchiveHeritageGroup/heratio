#!/bin/bash
# =============================================================================
# heratio-vm.sh — provision a libvirt VM that runs Heratio in Docker
#
# What it does:
#   1. Downloads Ubuntu 24.04 LTS cloud image (one-time, cached)
#   2. Generates a cloud-init seed (user, ssh key, packages, docker install)
#   3. Creates a fresh qcow2 disk + virt-installs an Ubuntu VM
#   4. Waits for the VM's IP, scp's this repo into it, brings up docker compose
#
# Result: a clean test environment at http://<vm-ip>:8088/
#
# Idempotent-ish:
#   - re-running deletes the existing VM (with --force) before re-creating
#   - the cloud image cache is reused
#
# Run on the libvirt host (this server):
#   sudo bin/heratio-vm.sh                 # default 'heratio-test'
#   sudo bin/heratio-vm.sh --name h2       # custom VM name
#   sudo bin/heratio-vm.sh --force         # destroy + recreate if exists
#
# @copyright  Johan Pieterse / Plain Sailing
# @license    AGPL-3.0-or-later
# =============================================================================

set -euo pipefail

VM_NAME="heratio-test"
VM_RAM_MB=4096
VM_CPUS=4
VM_DISK_GB=40
NET_BRIDGE="br0"
FORCE=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --name)   VM_NAME="$2"; shift 2 ;;
        --ram)    VM_RAM_MB="$2"; shift 2 ;;
        --cpus)   VM_CPUS="$2"; shift 2 ;;
        --disk)   VM_DISK_GB="$2"; shift 2 ;;
        --bridge) NET_BRIDGE="$2"; shift 2 ;;
        --force)  FORCE=1; shift ;;
        *) echo "unknown arg: $1"; exit 1 ;;
    esac
done

[[ $EUID -eq 0 ]] || { echo "needs sudo"; exit 1; }

IMG_DIR=/var/lib/libvirt/images
CACHE_DIR=/var/lib/libvirt/cache
SEED_DIR=$IMG_DIR/$VM_NAME-seed
DISK=$IMG_DIR/$VM_NAME.qcow2
SEED_ISO=$SEED_DIR/seed.iso

CLOUD_IMG_URL=https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img
CLOUD_IMG=$CACHE_DIR/noble-server-cloudimg-amd64.img

mkdir -p "$IMG_DIR" "$CACHE_DIR" "$SEED_DIR"

# ── 1. cache the cloud image ─────────────────────────────────────────────────
if [[ ! -f "$CLOUD_IMG" ]]; then
    echo "==> downloading Ubuntu 24.04 cloud image (~600 MB, one-time)"
    curl -fL -o "$CLOUD_IMG.tmp" "$CLOUD_IMG_URL"
    mv "$CLOUD_IMG.tmp" "$CLOUD_IMG"
fi

# ── 2. destroy old VM if --force ─────────────────────────────────────────────
if virsh dominfo "$VM_NAME" >/dev/null 2>&1; then
    if [[ $FORCE -eq 1 ]]; then
        echo "==> destroying existing VM $VM_NAME"
        virsh destroy "$VM_NAME" 2>/dev/null || true
        virsh undefine "$VM_NAME" --remove-all-storage 2>/dev/null || true
    else
        echo "VM $VM_NAME already exists. Pass --force to rebuild."
        exit 1
    fi
fi
rm -f "$DISK"

# ── 3. cloud-init seed (user-data + meta-data → ISO) ─────────────────────────
SSH_PUB=$(cat /root/.ssh/id_ed25519.pub 2>/dev/null \
       || cat /root/.ssh/id_rsa.pub 2>/dev/null)
[[ -n "$SSH_PUB" ]] || { echo "no ssh pubkey at /root/.ssh/"; exit 1; }

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
  - gnupg
  - rsync
  - git
  - apt-transport-https
runcmd:
  # Docker official install — script-form, idempotent.
  - install -m 0755 -d /etc/apt/keyrings
  - curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  - chmod a+r /etc/apt/keyrings/docker.gpg
  - |
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \$(. /etc/os-release && echo \$VERSION_CODENAME) stable" \
      > /etc/apt/sources.list.d/docker.list
  - apt-get update
  - DEBIAN_FRONTEND=noninteractive apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  - usermod -aG docker ahgadmin
  # ES needs a higher max_map_count
  - sysctl -w vm.max_map_count=262144
  - echo "vm.max_map_count=262144" >> /etc/sysctl.conf
  # Mark provisioning done
  - touch /var/log/heratio-vm-ready
final_message: "Heratio VM ready. SSH: ahgadmin@<ip>"
EOF

cat > "$SEED_DIR/meta-data" <<EOF
instance-id: $VM_NAME
local-hostname: $VM_NAME
EOF

genisoimage -output "$SEED_ISO" -volid cidata -joliet -rock \
            "$SEED_DIR/user-data" "$SEED_DIR/meta-data" >/dev/null 2>&1

# ── 4. clone the cloud image into the VM disk and resize ─────────────────────
echo "==> creating $VM_DISK_GB GB disk"
cp --reflink=auto "$CLOUD_IMG" "$DISK"
qemu-img resize "$DISK" "${VM_DISK_GB}G"

# ── 5. virt-install (no graphics, serial console for `virsh console`) ────────
echo "==> creating VM $VM_NAME ($VM_CPUS vCPU, $VM_RAM_MB MB RAM, $VM_DISK_GB GB disk)"
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

# ── 6. wait for IP, then deploy Heratio ──────────────────────────────────────
echo "==> waiting for VM to acquire DHCP lease"
for i in {1..60}; do
    IP=$(virsh domifaddr "$VM_NAME" 2>/dev/null \
         | awk '/ipv4/{split($4,a,"/"); print a[1]; exit}')
    [[ -n "$IP" ]] && break
    sleep 2
done

if [[ -z "$IP" ]]; then
    echo "no IP after 2 min — check 'virsh console $VM_NAME'"
    exit 1
fi
echo "==> VM IP: $IP"

echo "==> waiting for cloud-init to finish (Docker install)"
for i in {1..120}; do
    if ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new \
           ahgadmin@"$IP" 'test -f /var/log/heratio-vm-ready' 2>/dev/null; then
        echo "==> cloud-init done"
        break
    fi
    sleep 5
done

echo "==> deploying repo + bringing up docker compose"
REPO=/usr/share/nginx/heratio
ssh ahgadmin@"$IP" 'mkdir -p ~/heratio'
rsync -a --delete \
    --exclude='.git' --exclude='node_modules' --exclude='vendor' \
    --exclude='storage/uploads' --exclude='storage/logs' \
    --exclude='public/build' --exclude='*.log' \
    "$REPO/" "ahgadmin@$IP:~/heratio/"

ssh ahgadmin@"$IP" '
    cd ~/heratio/docker
    [ -f .env.docker ] || cp .env.docker.example .env.docker
    docker compose --env-file .env.docker up -d --build
    docker compose ps
'

echo
echo "==================================================================="
echo "Heratio test VM ready"
echo "  Name:   $VM_NAME"
echo "  IP:     $IP"
echo "  Web:    http://$IP:8088/"
echo "  SSH:    ssh ahgadmin@$IP"
echo "  Console: virsh console $VM_NAME   (Ctrl-] to detach)"
echo "  Logs:   ssh ahgadmin@$IP 'cd ~/heratio/docker && docker compose logs -f'"
echo "==================================================================="
